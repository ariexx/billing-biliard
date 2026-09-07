<?php

namespace Tests\Feature;

use App\Exports\RiwayatMinumanExport;
use App\Exports\RiwayatOrderExport;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class RiwayatTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private Payment $tunai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::factory()->create(['role' => 'cashier', 'name' => 'Kasir Satu']);
        $this->tunai = Payment::create(['name' => 'Tunai', 'is_active' => true]);
    }

    private function meja(string $nama = 'Meja 1'): Product
    {
        return Product::firstOrCreate(
            ['product_code' => $nama],
            ['type' => 'billiard', 'name' => $nama, 'price' => 0]
        );
    }

    /**
     * Satu order biliar lengkap pada waktu tertentu, milik kasir tertentu.
     */
    private function order(User $kasir, $waktu, int $harga = 50000, bool $lunas = true): Order
    {
        $waktu = Carbon::parse($waktu);
        $this->actingAs($kasir);

        $meja = $this->meja();

        $order = Order::create(['payment_uuid' => $this->tunai->uuid]);
        $order->forceFill(['created_at' => $waktu, 'paid_at' => $lunas ? $waktu : null])->save();

        $sesi = $order->activeOrders()->create([
            'product_uuid' => $meja->uuid,
            'hour' => 1,
            'is_active' => false,
            'started_at' => $waktu,
            'end_at' => $waktu->copy()->addHour(),
            'hour_type' => 'regular',
        ]);

        $order->orderItems()->create([
            'product_uuid' => $meja->uuid,
            'quantity' => 1,
            'price' => $harga,
            'hour' => 1,
            'active_order_unique_id' => $sesi->unique_id,
        ])->forceFill(['created_at' => $waktu])->save();

        return $order;
    }

    private function tambahProduk(Order $order, string $nama, string $tipe, int $qty, int $harga, $waktu): void
    {
        $produk = Product::firstOrCreate(
            ['product_code' => $nama],
            ['type' => $tipe, 'name' => $nama, 'price' => intdiv($harga, max($qty, 1))]
        );

        $order->orderItems()->create([
            'product_uuid' => $produk->uuid,
            'quantity' => $qty,
            'price' => $harga,
        ])->forceFill(['created_at' => Carbon::parse($waktu)])->save();
    }

    // ---------------------------------------------------------------- login

    /** @test */
    public function halaman_login_memakai_tampilan_baru_tanpa_navbar_aplikasi(): void
    {
        $html = $this->get(route('login'))->assertSuccessful()->getContent();

        $this->assertStringContainsString('Masuk untuk mulai melayani', $html);
        $this->assertStringContainsString('Ingat saya di perangkat ini', $html);
        // Layout auth berdiri sendiri: navbar aplikasi tidak boleh ikut dirender.
        $this->assertStringNotContainsString('navbar-toggler', $html);
    }

    /** @test */
    public function pesan_kesalahan_login_sama_untuk_email_ada_maupun_tidak(): void
    {
        // Yang membocorkan email mana yang terdaftar bukan isian form yang terisi
        // ulang (itu diketik penggunanya sendiri), melainkan pesan error yang
        // membedakan "email tidak ada" dari "password salah".
        $emailTidakAda = $this->from(route('login'))
            ->post(route('login'), ['email' => 'bukan@ada.test', 'password' => 'salah-sekali'])
            ->assertRedirect(route('login'))
            ->getSession()->get('errors')->first('email');

        $passwordSalah = $this->from(route('login'))
            ->post(route('login'), ['email' => $this->kasir->email, 'password' => 'salah-sekali'])
            ->assertRedirect(route('login'))
            ->getSession()->get('errors')->first('email');

        $this->assertSame($emailTidakAda, $passwordSalah);
        $this->assertNotEmpty($emailTidakAda);
    }

    // -------------------------------------------------- riwayat order biliar

    /** @test */
    public function riwayat_order_bawaan_menampilkan_hari_ini(): void
    {
        $this->order($this->kasir, now()->setTime(14, 0), 50000);
        $this->order($this->kasir, now()->subDays(3)->setTime(14, 0), 90000);

        $this->actingAs($this->kasir)
            ->get(route('order-history'))
            ->assertSuccessful()
            ->assertSee('Rp 50.000,00')
            ->assertDontSee('Rp 90.000,00');
    }

    /** @test */
    public function rentang_tanggal_mengubah_angka_kpi(): void
    {
        $this->order($this->kasir, '2026-03-10 14:00', 50000);
        $this->order($this->kasir, '2026-03-12 14:00', 70000);
        $this->order($this->kasir, '2026-04-01 14:00', 90000);

        $this->actingAs($this->kasir)
            ->get(route('order-history', ['dari' => '2026-03-01', 'sampai' => '2026-03-31']))
            ->assertSuccessful()
            ->assertSee('Rp 120.000,00')   // 50rb + 70rb, tanpa yang April
            ->assertSee('Rp 60.000,00');   // rata-rata per order
    }

    /** @test */
    public function tabel_memakai_rentang_yang_sama_dengan_kartu_kpi(): void
    {
        // Dulu kartunya berbunyi "hari ini" sementara tabelnya memuat seluruh
        // riwayat -- angka di atas dan daftar di bawah bercerita beda.
        $dalam = $this->order($this->kasir, '2026-03-10 14:00', 50000);
        $luar = $this->order($this->kasir, '2026-04-01 14:00', 90000);

        $data = $this->actingAs($this->kasir)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('order-history', [
                'dari' => '2026-03-01', 'sampai' => '2026-03-31',
                'draw' => 1, 'start' => 0, 'length' => 10,
            ]))->assertSuccessful()->json();

        $this->assertSame(1, $data['recordsFiltered']);
        $this->assertStringContainsString($dalam->order_number, json_encode($data['data']));
        $this->assertStringNotContainsString($luar->order_number, json_encode($data['data']));
    }

    /** @test */
    public function kasir_hanya_melihat_ordernya_sendiri_admin_melihat_semua(): void
    {
        $kasirLain = User::factory()->create(['role' => 'cashier', 'name' => 'Kasir Dua']);
        $this->order($this->kasir, now()->setTime(10, 0), 50000);
        $this->order($kasirLain, now()->setTime(11, 0), 70000);

        // Kasir: hanya ordernya sendiri.
        $this->actingAs($this->kasir)
            ->get(route('order-history'))
            ->assertSee('Rp 50.000,00')
            ->assertDontSee('Rp 120.000,00');

        // Admin: seluruhnya.
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('order-history'))
            ->assertSee('Rp 120.000,00');
    }

    /** @test */
    public function tanggal_ngawur_jatuh_ke_hari_ini_bukan_error(): void
    {
        $this->order($this->kasir, now()->setTime(14, 0), 50000);

        $this->actingAs($this->kasir)
            ->get(route('order-history', ['dari' => 'kemarin-lusa', 'sampai' => '???']))
            ->assertSuccessful()
            ->assertSee('Rp 50.000,00');
    }

    /** @test */
    public function rentang_terbalik_ditukar_bukan_menghasilkan_laporan_kosong(): void
    {
        $this->order($this->kasir, '2026-03-10 14:00', 50000);

        $this->actingAs($this->kasir)
            ->get(route('order-history', ['dari' => '2026-03-31', 'sampai' => '2026-03-01']))
            ->assertSuccessful()
            ->assertSee('Rp 50.000,00');
    }

    // ------------------------------------------------ riwayat minuman/snack

    /** @test */
    public function riwayat_minuman_ikut_menghitung_snack_dan_lainnya(): void
    {
        // Versi lama hanya menghitung type 'drink', jadi snack yang dijual di kasir
        // yang sama tidak muncul di mana pun.
        $order = $this->order($this->kasir, now()->setTime(14, 0), 50000);
        $this->tambahProduk($order, 'Kopi', 'drink', 2, 20000, now()->setTime(14, 5));
        $this->tambahProduk($order, 'Kacang', 'snack', 1, 8000, now()->setTime(14, 6));

        $this->actingAs($this->kasir)
            ->get(route('order-history.drinks'))
            ->assertSuccessful()
            ->assertSee('Kopi')
            ->assertSee('Kacang')
            ->assertSee('Rp 28.000,00');
    }

    /** @test */
    public function riwayat_minuman_menghormati_rentang_tanggal(): void
    {
        $lama = $this->order($this->kasir, '2026-03-10 14:00', 50000);
        $this->tambahProduk($lama, 'Kopi', 'drink', 2, 20000, '2026-03-10 14:05');

        $baru = $this->order($this->kasir, '2026-04-05 14:00', 50000);
        $this->tambahProduk($baru, 'Teh', 'drink', 1, 5000, '2026-04-05 14:05');

        $this->actingAs($this->kasir)
            ->get(route('order-history.drinks', ['dari' => '2026-03-01', 'sampai' => '2026-03-31']))
            ->assertSuccessful()
            ->assertSee('Kopi')
            ->assertDontSee('Teh');
    }

    /** @test */
    public function kasir_tidak_melihat_penjualan_kasir_lain_di_riwayat_minuman(): void
    {
        $kasirLain = User::factory()->create(['role' => 'cashier', 'name' => 'Kasir Dua']);
        $order = $this->order($kasirLain, now()->setTime(14, 0), 50000);
        $this->tambahProduk($order, 'Kopi Rahasia', 'drink', 1, 10000, now()->setTime(14, 5));

        $this->actingAs($this->kasir)
            ->get(route('order-history.drinks'))
            ->assertSuccessful()
            ->assertDontSee('Kopi Rahasia');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('order-history.drinks'))
            ->assertSee('Kopi Rahasia');
    }

    /** @test */
    public function riwayat_minuman_dipaginasi(): void
    {
        $order = $this->order($this->kasir, now()->setTime(14, 0), 50000);

        for ($i = 1; $i <= 30; $i++) {
            $this->tambahProduk($order, 'Produk '.$i, 'drink', 1, 1000, now()->setTime(14, 10));
        }

        $html = $this->actingAs($this->kasir)
            ->get(route('order-history.drinks'))
            ->assertSuccessful()
            ->getContent();

        // 25 per halaman: tautan halaman berikutnya harus muncul.
        $this->assertStringContainsString('page=2', $html);
    }

    // ------------------------------------------------------------- export

    /** @test */
    public function export_riwayat_order_menghormati_rentang_dan_peran(): void
    {
        Excel::fake();

        $this->actingAs($this->kasir)
            ->get(route('order-history.export', ['dari' => '2026-03-01', 'sampai' => '2026-03-31']))
            ->assertSuccessful();

        Excel::assertDownloaded('riwayat-order-20260301-20260331.xlsx');
    }

    /** @test */
    public function export_kasir_tidak_memuat_order_kasir_lain(): void
    {
        $kasirLain = User::factory()->create(['role' => 'cashier']);
        $milikSaya = $this->order($this->kasir, now()->setTime(10, 0), 50000);
        $milikOrang = $this->order($kasirLain, now()->setTime(11, 0), 70000);

        // Pembatasan peran diuji pada kelas exportnya, bukan sekadar pada tampilan:
        // tanpa itu kasir bisa mengunduh seluruh order lewat URL export.
        $baris = (new RiwayatOrderExport(today()->startOfDay(), today()->endOfDay(), $this->kasir))
            ->collection();

        $nomor = $baris->pluck('order_number')->all();

        $this->assertContains($milikSaya->order_number, $nomor);
        $this->assertNotContains($milikOrang->order_number, $nomor);
    }

    /** @test */
    public function export_riwayat_minuman_terunduh(): void
    {
        Excel::fake();

        $this->actingAs($this->kasir)
            ->get(route('order-history.drinks.export', ['dari' => '2026-03-01', 'sampai' => '2026-03-31']))
            ->assertSuccessful();

        Excel::assertDownloaded('riwayat-minuman-20260301-20260331.xlsx');
    }
}
