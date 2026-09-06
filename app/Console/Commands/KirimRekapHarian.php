<?php

namespace App\Console\Commands;

use App\Services\Rekap;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Mengirim rekap harian ke Telegram.
 *
 * PC kasir dimatikan di luar jam operasional, jadi jadwal "kirim jam 22:00"
 * saja tidak cukup: kalau PC sudah mati, rekapnya hilang selamanya. Command ini
 * karena itu dijalankan tiap jam dan mengejar hari yang terlewat, dengan
 * penanda tanggal terakhir supaya tidak mengirim ganda.
 */
class KirimRekapHarian extends Command
{
    protected $signature = 'rekap:telegram
                            {--tanggal= : Kirim rekap tanggal tertentu (Y-m-d), abaikan penjadwalan}
                            {--paksa : Kirim ulang walau tanggal itu sudah pernah dikirim}';

    protected $description = 'Kirim rekap harian ke Telegram';

    public function __construct(private TelegramNotifier $telegram)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->telegram->aktif()) {
            $this->warn('Telegram belum dikonfigurasi, rekap dilewati.');

            return self::SUCCESS;
        }

        $tanggalList = $this->option('tanggal')
            ? [Carbon::parse($this->option('tanggal'))->startOfDay()]
            : $this->tanggalJatuhTempo();

        if (empty($tanggalList)) {
            return self::SUCCESS;
        }

        foreach ($tanggalList as $tanggal) {
            try {
                $this->telegram->kirim($this->susunPesan($tanggal));
            } catch (\Throwable $e) {
                $this->error('Gagal mengirim rekap '.$tanggal->toDateString().': '.$e->getMessage());
                \Log::channel('daily')->error('Rekap Telegram gagal: '.$e->getMessage());

                // Penanda tidak digeser, jadi tanggal ini dicoba lagi jam berikutnya.
                return self::FAILURE;
            }

            $this->catatTerkirim($tanggal);
            $this->info('Rekap '.$tanggal->toDateString().' terkirim.');
            \Log::channel('daily')->info('Rekap Telegram terkirim: '.$tanggal->toDateString());
        }

        return self::SUCCESS;
    }

    /**
     * Tanggal yang rekapnya sudah jatuh tempo tapi belum pernah dikirim.
     *
     * @return array<int, Carbon>
     */
    private function tanggalJatuhTempo(): array
    {
        $jam = config('telegram.report_hour');

        // Hari terakhir yang jatuh tempo: hari ini kalau jam kirimnya sudah lewat,
        // kalau belum ya kemarin.
        $terakhirJatuhTempo = now()->hour >= $jam
            ? today()
            : today()->subDay();

        $terakhirTerkirim = $this->terakhirTerkirim();

        if ($this->option('paksa')) {
            return [$terakhirJatuhTempo];
        }

        if ($terakhirTerkirim && $terakhirTerkirim->gte($terakhirJatuhTempo)) {
            return [];
        }

        $mulai = $terakhirTerkirim
            ? $terakhirTerkirim->copy()->addDay()
            : $terakhirJatuhTempo;

        // Libur panjang tidak boleh membanjiri chat dengan puluhan pesan.
        $batas = $terakhirJatuhTempo->copy()->subDays(config('telegram.max_backlog_days') - 1);
        if ($mulai->lt($batas)) {
            $mulai = $batas;
        }

        $hasil = [];
        for ($t = $mulai->copy(); $t->lte($terakhirJatuhTempo); $t->addDay()) {
            $hasil[] = $t->copy();
        }

        return $hasil;
    }

    private function penandaPath(): string
    {
        return storage_path('app/rekap-telegram-terakhir.txt');
    }

    private function terakhirTerkirim(): ?Carbon
    {
        if (! file_exists($this->penandaPath())) {
            return null;
        }

        try {
            return Carbon::parse(trim(file_get_contents($this->penandaPath())))->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function catatTerkirim(Carbon $tanggal): void
    {
        $sebelumnya = $this->terakhirTerkirim();

        // Penanda hanya maju, supaya --tanggal untuk hari lampau tidak membuat
        // rekap hari-hari sesudahnya terkirim ulang.
        if ($sebelumnya && $sebelumnya->gte($tanggal)) {
            return;
        }

        file_put_contents($this->penandaPath(), $tanggal->toDateString());
    }

    public function susunPesan(Carbon $tanggal): string
    {
        $rekap = Rekap::harian($tanggal);
        $r = $rekap->ringkasan();
        $e = fn ($teks) => TelegramNotifier::escape($teks);

        $baris = [];
        $baris[] = '<b>Rekap Harian</b> — '.$tanggal->translatedFormat('l, d/m/Y');
        $baris[] = $e(config('app.name'));
        $baris[] = '';
        $baris[] = '<b>Omzet: '.$e(rupiah($r['omzet'])).'</b>';
        $baris[] = '• Biliar: '.$e(rupiah($r['biliar']));
        $baris[] = '• Lainnya: '.$e(rupiah($r['lain']));
        $baris[] = 'Order: '.$r['jumlah_order'].' • Rata-rata: '.$e(rupiah($r['rata_order']));

        if ($r['belum_lunas'] > 0) {
            $baris[] = '⚠️ <b>Belum dibayar: '.$r['belum_lunas'].' order</b>';
        }

        if ($r['jumlah_order'] === 0) {
            $baris[] = '';
            $baris[] = '<i>Tidak ada transaksi hari ini.</i>';

            return implode("\n", $baris);
        }

        $metode = $rekap->perMetodeBayar();
        if ($metode->isNotEmpty()) {
            $baris[] = '';
            $baris[] = '<b>Metode Pembayaran</b> <i>(hanya yang lunas)</i>';
            foreach ($metode as $m) {
                $baris[] = '• '.$e($m->metode).': '.$m->jumlah.' order — '.$e(rupiah((int) $m->total_omzet));
            }
        }

        $kasir = $rekap->perKasir();
        if ($kasir->isNotEmpty()) {
            $baris[] = '';
            $baris[] = '<b>Kasir</b>';
            foreach ($kasir as $k) {
                $baris[] = '• '.$e($k->kasir).': '.$k->jumlah.' order — '.$e(rupiah((int) $k->total_omzet));
            }
        }

        $meja = $rekap->pemanfaatanMeja()->take(5);
        if ($meja->isNotEmpty()) {
            $baris[] = '';
            $baris[] = '<b>Meja Teratas</b>';
            foreach ($meja as $m) {
                $baris[] = '• '.$e($m['meja']).': '.$m['jumlah_sesi'].' sesi, '
                    .number_format($m['menit'] / 60, 1, ',', '.').' jam — '.$e(rupiah($m['pendapatan']));
            }
        }

        $produk = $rekap->produkTerlaris(5);
        if ($produk->isNotEmpty()) {
            $baris[] = '';
            $baris[] = '<b>Produk Terlaris</b>';
            foreach ($produk as $p) {
                $baris[] = '• '.$e($p->produk).': '.$p->qty.'x — '.$e(rupiah((int) $p->total));
            }
        }

        return implode("\n", $baris);
    }
}
