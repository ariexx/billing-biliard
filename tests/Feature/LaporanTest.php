<?php

namespace Tests\Feature;

use App\Filament\Pages\Laporan;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Payment $tunai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
        $this->tunai = Payment::create(['name' => 'Tunai', 'is_active' => true]);
    }

    private function meja(string $nama): Product
    {
        return Product::create(['type' => 'billiard', 'product_code' => $nama, 'name' => $nama, 'price' => 0]);
    }

    /**
     * Satu sesi meja lengkap dengan item pada waktu tertentu.
     */
    private function sesi(Product $meja, $mulai, int $menit, int $harga, bool $lunas = true): Order
    {
        $mulai = \Illuminate\Support\Carbon::parse($mulai);

        $order = Order::create(['payment_uuid' => $this->tunai->uuid]);
        $order->forceFill([
            'created_at' => $mulai,
            'paid_at' => $lunas ? $mulai->copy()->addMinutes($menit) : null,
        ])->save();

        $sesi = $order->activeOrders()->create([
            'product_uuid' => $meja->uuid,
            'hour' => 1,
            'is_active' => false,
            'started_at' => $mulai,
            'end_at' => $mulai->copy()->addMinutes($menit),
            'hour_type' => 'regular',
        ]);

        $item = $order->orderItems()->create([
            'product_uuid' => $meja->uuid,
            'quantity' => 1,
            'price' => $harga,
            'hour' => 1,
            'active_order_unique_id' => $sesi->unique_id,
        ]);

        $item->forceFill(['created_at' => $mulai])->save();

        return $order;
    }

    private function laporan(string $dari, string $sampai)
    {
        return Livewire::test(Laporan::class)->set('dari', $dari)->set('sampai', $sampai);
    }

    /** @test */
    public function halaman_laporan_terbuka_untuk_admin(): void
    {
        Livewire::test(Laporan::class)
            ->assertSuccessful()
            ->assertSee('Omzet per Hari')
            ->assertSee('Pemanfaatan Meja')
            ->assertSee('Jam Tersibuk');
    }

    /** @test */
    public function omzet_hanya_menghitung_transaksi_dalam_rentang(): void
    {
        $meja = $this->meja('Meja 1');
        $this->sesi($meja, '2026-03-10 14:00', 60, 50000);
        $this->sesi($meja, '2026-03-15 14:00', 60, 70000);
        $this->sesi($meja, '2026-04-02 14:00', 60, 90000); // di luar rentang

        $ringkasan = $this->laporan('2026-03-01', '2026-03-31')->instance()->ringkasan;

        $this->assertSame(120000, $ringkasan['omzet']);
        $this->assertSame(2, $ringkasan['jumlah_order']);
        $this->assertSame(60000, $ringkasan['rata_order']);
    }

    /** @test */
    public function order_yang_dihapus_tidak_ikut_dihitung(): void
    {
        // SoftDeletes hanya membatasi tabel modelnya sendiri; pada join mentah
        // orders.deleted_at harus difilter manual.
        $meja = $this->meja('Meja 1');
        $this->sesi($meja, '2026-03-10 14:00', 60, 50000);
        $dihapus = $this->sesi($meja, '2026-03-11 14:00', 60, 999000);
        $dihapus->delete();

        $this->assertSame(50000, $this->laporan('2026-03-01', '2026-03-31')->instance()->ringkasan['omzet']);
    }

    /** @test */
    public function omzet_dipecah_per_hari(): void
    {
        $meja = $this->meja('Meja 1');
        $this->sesi($meja, '2026-03-10 14:00', 60, 50000);
        $this->sesi($meja, '2026-03-10 18:00', 60, 30000);
        $this->sesi($meja, '2026-03-12 14:00', 60, 20000);

        $perHari = $this->laporan('2026-03-01', '2026-03-31')->instance()->perHari;

        $this->assertCount(2, $perHari);
        $this->assertSame(80000, (int) $perHari->first()->total);
    }

    /** @test */
    public function rekap_per_metode_bayar_hanya_order_lunas(): void
    {
        $meja = $this->meja('Meja 1');
        $qris = Payment::create(['name' => 'QRIS', 'is_active' => true]);

        $this->sesi($meja, '2026-03-10 14:00', 60, 50000, lunas: true);
        $belum = $this->sesi($meja, '2026-03-11 14:00', 60, 70000, lunas: false);
        $belum->update(['payment_uuid' => $qris->uuid]);

        $rekap = $this->laporan('2026-03-01', '2026-03-31')->instance()->perMetodeBayar;

        $this->assertCount(1, $rekap);
        $this->assertSame('Tunai', $rekap->first()->metode);
        $this->assertSame(50000, (int) $rekap->first()->total_omzet);
    }

    /** @test */
    public function rekap_per_kasir_menjumlahkan_ordernya(): void
    {
        $meja = $this->meja('Meja 1');
        $this->sesi($meja, '2026-03-10 14:00', 60, 50000);
        $this->sesi($meja, '2026-03-11 14:00', 60, 30000);

        $rekap = $this->laporan('2026-03-01', '2026-03-31')->instance()->perKasir;

        $this->assertCount(1, $rekap);
        $this->assertSame(80000, (int) $rekap->first()->total_omzet);
        $this->assertSame(2, (int) $rekap->first()->jumlah);
    }

    /** @test */
    public function pemanfaatan_meja_menghitung_jam_sesi_dan_pendapatan(): void
    {
        $m1 = $this->meja('Meja 1');
        $m2 = $this->meja('Meja 2');

        $this->sesi($m1, '2026-03-10 14:00', 120, 100000);
        $this->sesi($m1, '2026-03-11 14:00', 60, 50000);
        $this->sesi($m2, '2026-03-10 14:00', 60, 40000);

        $pemanfaatan = $this->laporan('2026-03-01', '2026-03-31')->instance()->pemanfaatanMeja;

        // Diurut dari pendapatan terbesar.
        $this->assertSame('Meja 1', $pemanfaatan[0]['meja']);
        $this->assertSame(2, $pemanfaatan[0]['jumlah_sesi']);
        $this->assertSame(180, $pemanfaatan[0]['menit']);
        $this->assertSame(150000, $pemanfaatan[0]['pendapatan']);

        $this->assertSame('Meja 2', $pemanfaatan[1]['meja']);
        $this->assertSame(60, $pemanfaatan[1]['menit']);
    }

    /** @test */
    public function jam_tersibuk_dikelompokkan_per_jam_mulai(): void
    {
        $meja = $this->meja('Meja 1');
        $this->sesi($meja, '2026-03-10 19:10', 60, 10000);
        $this->sesi($meja, '2026-03-11 19:40', 60, 10000);
        $this->sesi($meja, '2026-03-12 14:00', 60, 10000);

        $jam = $this->laporan('2026-03-01', '2026-03-31')->instance()->jamTersibuk;

        $puncak = $jam->firstWhere('jam', 19);
        $this->assertNotNull($puncak);
        $this->assertSame(2, $puncak->jumlah);
        $this->assertSame(1, $jam->firstWhere('jam', 14)->jumlah);
    }

    /** @test */
    public function produk_terlaris_tidak_menyertakan_meja(): void
    {
        $meja = $this->meja('Meja 1');
        $order = $this->sesi($meja, '2026-03-10 14:00', 60, 50000);

        $kopi = Product::create(['type' => 'drink', 'product_code' => 'KP', 'name' => 'Kopi', 'price' => 10000]);
        $order->orderItems()->create(['product_uuid' => $kopi->uuid, 'quantity' => 3, 'price' => 30000])
            ->forceFill(['created_at' => '2026-03-10 14:30'])->save();

        $terlaris = $this->laporan('2026-03-01', '2026-03-31')->instance()->produkTerlaris;

        $this->assertCount(1, $terlaris);
        $this->assertSame('Kopi', $terlaris->first()->produk);
        $this->assertSame(3, (int) $terlaris->first()->qty);
    }

    /** @test */
    public function rentang_kosong_tidak_error_dan_menampilkan_pesan(): void
    {
        $this->laporan('2020-01-01', '2020-01-31')
            ->assertSuccessful()
            ->assertSee('Tidak ada transaksi pada rentang ini');
    }
}
