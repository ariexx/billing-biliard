<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramRekapTest extends TestCase
{
    use RefreshDatabase;

    private string $penanda;

    /** Respons Telegram tiruan; bisa diubah per test. */
    private array $responTelegram = ['ok' => true];

    private int $statusTelegram = 200;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telegram.bot_token' => 'token-uji',
            'telegram.chat_id' => '12345',
            'telegram.report_hour' => 22,
            'telegram.max_backlog_days' => 7,
        ]);

        $this->penanda = storage_path('app/rekap-telegram-terakhir.txt');
        @unlink($this->penanda);

        // Satu stub yang responsnya bisa diubah: memanggil Http::fake() lagi di
        // dalam test TIDAK menimpa stub ini -- stub pertama yang menang.
        Http::fake(fn () => Http::response($this->responTelegram, $this->statusTelegram));
    }

    protected function tearDown(): void
    {
        @unlink($this->penanda);

        parent::tearDown();
    }

    private function transaksi($tanggal, int $harga = 50000, bool $lunas = true): Order
    {
        $tanggal = Carbon::parse($tanggal);

        $meja = Product::firstOrCreate(
            ['product_code' => 'M1'],
            ['type' => 'billiard', 'name' => 'Meja 1', 'price' => 0]
        );

        $order = Order::create([
            'payment_uuid' => Payment::firstOrCreate(['name' => 'Tunai'], ['is_active' => true])->uuid,
        ]);
        $order->forceFill(['created_at' => $tanggal, 'paid_at' => $lunas ? $tanggal : null])->save();

        $sesi = $order->activeOrders()->create([
            'product_uuid' => $meja->uuid,
            'hour' => 1,
            'is_active' => false,
            'started_at' => $tanggal,
            'end_at' => $tanggal->copy()->addHour(),
            'hour_type' => 'regular',
        ]);

        $order->orderItems()->create([
            'product_uuid' => $meja->uuid,
            'quantity' => 1,
            'price' => $harga,
            'hour' => 1,
            'active_order_unique_id' => $sesi->unique_id,
        ])->forceFill(['created_at' => $tanggal])->save();

        return $order;
    }

    private function pesanTerkirim(): array
    {
        $pesan = [];

        foreach (Http::recorded() as [$request]) {
            /** @var Request $request */
            if (str_contains($request->url(), '/sendMessage')) {
                $pesan[] = $request['text'];
            }
        }

        return $pesan;
    }

    /** @test */
    public function rekap_berisi_angka_yang_sama_dengan_halaman_laporan(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->transaksi('2026-03-10 14:00', 50000);
        $this->transaksi('2026-03-10 18:00', 30000);

        $this->artisan('rekap:telegram', ['--tanggal' => '2026-03-10'])->assertSuccessful();

        $pesan = $this->pesanTerkirim();
        $this->assertCount(1, $pesan);
        $this->assertStringContainsString('Rp 80.000', $pesan[0]);
        $this->assertStringContainsString('Order: 2', $pesan[0]);
        $this->assertStringContainsString('Meja 1', $pesan[0]);
    }

    /** @test */
    public function order_belum_dibayar_ditandai_di_rekap(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier']));
        $this->transaksi('2026-03-10 14:00', 50000, lunas: false);

        $this->artisan('rekap:telegram', ['--tanggal' => '2026-03-10']);

        $this->assertStringContainsString('Belum dibayar: 1 order', $this->pesanTerkirim()[0]);
    }

    /** @test */
    public function hari_tanpa_transaksi_tetap_dikirim_dengan_keterangan(): void
    {
        $this->artisan('rekap:telegram', ['--tanggal' => '2026-03-10'])->assertSuccessful();

        $this->assertStringContainsString('Tidak ada transaksi', $this->pesanTerkirim()[0]);
    }

    /** @test */
    public function nama_produk_berkarakter_html_tidak_merusak_pesan(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier']));
        $order = $this->transaksi('2026-03-10 14:00', 50000);

        $nakal = Product::create([
            'type' => 'drink', 'product_code' => 'X',
            'name' => 'Teh <b>Manis</b> & Es', 'price' => 5000,
        ]);
        $order->orderItems()->create(['product_uuid' => $nakal->uuid, 'quantity' => 1, 'price' => 5000])
            ->forceFill(['created_at' => '2026-03-10 14:30'])->save();

        $this->artisan('rekap:telegram', ['--tanggal' => '2026-03-10']);

        // parse_mode HTML: tag mentah dari nama produk harus sudah di-escape.
        $this->assertStringContainsString('Teh &lt;b&gt;Manis&lt;/b&gt; &amp; Es', $this->pesanTerkirim()[0]);
    }

    /** @test */
    public function tidak_mengirim_apa_pun_kalau_telegram_belum_dikonfigurasi(): void
    {
        config(['telegram.bot_token' => null]);

        $this->artisan('rekap:telegram')->assertSuccessful();

        Http::assertNothingSent();
    }

    /** @test */
    public function belum_lewat_jam_kirim_maka_yang_dikirim_rekap_kemarin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-11 09:00'));

        $this->artisan('rekap:telegram')->assertSuccessful();

        $this->assertStringContainsString('10/03/2026', $this->pesanTerkirim()[0]);
        Carbon::setTestNow();
    }

    /** @test */
    public function sudah_lewat_jam_kirim_maka_yang_dikirim_rekap_hari_ini(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-11 22:30'));

        $this->artisan('rekap:telegram')->assertSuccessful();

        $this->assertStringContainsString('11/03/2026', $this->pesanTerkirim()[0]);
        Carbon::setTestNow();
    }

    /** @test */
    public function tidak_mengirim_dua_kali_untuk_tanggal_yang_sama(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-11 22:30'));

        $this->artisan('rekap:telegram')->assertSuccessful();
        $this->artisan('rekap:telegram')->assertSuccessful();

        $this->assertCount(1, $this->pesanTerkirim());
        Carbon::setTestNow();
    }

    /** @test */
    public function rekap_hari_yang_terlewat_dikirim_susulan(): void
    {
        // PC kasir mati beberapa hari; terakhir terkirim tanggal 8.
        file_put_contents($this->penanda, '2026-03-08');
        Carbon::setTestNow(Carbon::parse('2026-03-11 09:00'));

        $this->artisan('rekap:telegram')->assertSuccessful();

        $pesan = $this->pesanTerkirim();

        // Jam 09:00 belum lewat jam kirim, jadi jatuh tempo terakhir = 10 Maret.
        $this->assertCount(2, $pesan);
        $this->assertStringContainsString('09/03/2026', $pesan[0]);
        $this->assertStringContainsString('10/03/2026', $pesan[1]);
        Carbon::setTestNow();
    }

    /** @test */
    public function susulan_dibatasi_supaya_tidak_membanjiri_chat(): void
    {
        config(['telegram.max_backlog_days' => 3]);
        file_put_contents($this->penanda, '2026-01-01');
        Carbon::setTestNow(Carbon::parse('2026-03-11 22:30'));

        $this->artisan('rekap:telegram')->assertSuccessful();

        $this->assertCount(3, $this->pesanTerkirim());
        Carbon::setTestNow();
    }

    /** @test */
    public function penanda_tidak_digeser_kalau_pengiriman_gagal(): void
    {
        $this->responTelegram = ['ok' => false, 'description' => 'chat not found'];
        $this->statusTelegram = 400;
        Carbon::setTestNow(Carbon::parse('2026-03-11 22:30'));

        $this->artisan('rekap:telegram')->assertFailed();

        // Tanpa penanda yang bergeser, jam berikutnya mencoba lagi.
        $this->assertFileDoesNotExist($this->penanda);
        Carbon::setTestNow();
    }

    /** @test */
    public function backup_dikirim_ke_telegram_hanya_kalau_opsinya_aktif(): void
    {
        // Folder terpisah: menunjuk storage/app/backups lalu mengosongkannya
        // berarti `php artisan test` menghapus backup sungguhan di mesin kasir.
        config(['backup.local_path' => 'backups-test']);

        $dir = storage_path('app/'.config('backup.local_path'));
        @mkdir($dir, 0755, true);
        foreach (glob($dir.'/*.sql.gz') ?: [] as $f) {
            @unlink($f);
        }

        \Illuminate\Support\Facades\Storage::fake('gdrive');
        $this->app[\Illuminate\Contracts\Console\Kernel::class]->registerCommand(new FakeBackupUntukTelegram());

        config(['telegram.send_backup' => false]);
        $this->artisan('backup:database', ['--no-upload' => true])->assertSuccessful();
        $this->assertCount(0, $this->dokumenTerkirim());

        config(['telegram.send_backup' => true]);
        $this->artisan('backup:database', ['--no-upload' => true])->assertSuccessful();
        $this->assertCount(1, $this->dokumenTerkirim());

        foreach (glob($dir.'/*.sql.gz') ?: [] as $f) {
            @unlink($f);
        }
    }

    private function dokumenTerkirim(): array
    {
        $dokumen = [];

        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), '/sendDocument')) {
                $dokumen[] = $request->url();
            }
        }

        return $dokumen;
    }
}

class FakeBackupUntukTelegram extends \App\Console\Commands\BackupDatabase
{
    protected function dump(): string
    {
        $dir = storage_path('app/'.config('backup.local_path'));

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.DIRECTORY_SEPARATOR.'uji-telegram-'.now()->format('Ymd-His-u').'.sql.gz';
        file_put_contents($path, gzencode('-- dump tiruan'));

        return $path;
    }
}
