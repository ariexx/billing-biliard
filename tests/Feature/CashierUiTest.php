<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Hour;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashierUiTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($this->kasir);
    }

    private function meja(string $nama): Product
    {
        $meja = Product::create(['type' => 'billiard', 'product_code' => $nama, 'name' => $nama, 'price' => 0]);
        $jam = Hour::create(['name' => '1 Jam', 'hour' => 1, 'type' => 'regular', 'price' => 50000]);
        $meja->hours()->attach([$jam->uuid]);

        return $meja;
    }

    private function sesi(Product $meja, string $tipe, int $mulaiMenitLalu, int $selesaiMenitDepan, int $harga): Order
    {
        $order = Order::create([
            'payment_uuid' => Payment::create(['name' => 'Cash', 'is_active' => true])->uuid,
        ]);

        $sesi = $order->activeOrders()->create([
            'product_uuid' => $meja->uuid,
            'hour' => 1,
            'is_active' => true,
            'started_at' => now()->subMinutes($mulaiMenitLalu),
            'end_at' => now()->addMinutes($selesaiMenitDepan),
            'hour_type' => $tipe,
        ]);

        $order->orderItems()->create([
            'product_uuid' => $meja->uuid,
            'quantity' => 1,
            'price' => $harga,
            'hour' => 1,
            'active_order_unique_id' => $sesi->unique_id,
        ]);

        return $order;
    }

    /** @test */
    public function meja_terpakai_ditandai_dan_tombol_mulai_disembunyikan(): void
    {
        $kosong = $this->meja('Meja Kosong');
        $terpakai = $this->meja('Meja Terpakai');
        $this->sesi($terpakai, 'regular', 10, 50, 50000);

        Livewire::test(\App\Http\Livewire\Product::class)
            ->assertSuccessful()
            ->assertSee('Meja Kosong')
            ->assertSee('Meja Terpakai')
            ->assertSee('Lihat Order')
            // Hanya meja kosong yang punya tombol mulai; meja terpakai tidak boleh
            // menawarkan aksi yang pasti ditolak server.
            ->assertSee("saveOrder('".$kosong->uuid."')", false)
            ->assertDontSee("saveOrder('".$terpakai->uuid."')", false);
    }

    /** @test */
    public function kartu_meja_reguler_menampilkan_sisa_waktu_terhitung_server(): void
    {
        // Angka di dalam <x-countdown> adalah hasil render server, jadi tetap
        // benar walau Alpine gagal dimuat dari CDN.
        $this->sesi($this->meja('Meja 1'), 'regular', 30, 90, 50000);

        Livewire::test(\App\Http\Livewire\ActiveOrder::class)
            ->assertSuccessful()
            ->assertSee('Sisa waktu')
            ->assertSee('x-text', false);
    }

    /** @test */
    public function kartu_main_bebas_menampilkan_tagihan_berjalan(): void
    {
        $this->sesi($this->meja('Meja 2'), 'free time', 90, 30, 500);

        Livewire::test(\App\Http\Livewire\ActiveOrder::class)
            ->assertSuccessful()
            ->assertSee('Main Bebas')
            ->assertSee('1j 30m')
            ->assertSee('Rp 45.000,00');
    }

    /** @test */
    public function dashboard_menampilkan_order_yang_belum_dibayar(): void
    {
        $meja = $this->meja('Meja 3');
        $order = $this->sesi($meja, 'regular', 120, -60, 50000);
        $order->activeOrders()->update(['is_active' => false]);

        $this->get(route('home'))
            ->assertSuccessful()
            ->assertSee('Belum Dibayar')
            ->assertSee($order->order_number);
    }

    /** @test */
    public function order_yang_sudah_lunas_tidak_muncul_di_panel_belum_dibayar(): void
    {
        $order = $this->sesi($this->meja('Meja 4'), 'regular', 120, -60, 50000);
        $order->activeOrders()->update(['is_active' => false]);
        $order->update(['paid_at' => now(), 'paid_by_uuid' => $this->kasir->uuid]);

        $this->get(route('home'))->assertSuccessful()->assertDontSee($order->order_number);
    }

    /** @test */
    public function halaman_order_menampilkan_form_bayar_dan_tombol_hapus_item(): void
    {
        $order = $this->sesi($this->meja('Meja 5'), 'regular', 120, -60, 50000);
        $order->activeOrders()->update(['is_active' => false]);

        $this->get(route('order.view', $order->uuid))
            ->assertSuccessful()
            ->assertSee('BELUM DIBAYAR')
            ->assertSee('Tandai Lunas')
            ->assertSee(route('order-item.destroy', $order->orderItems->first()->uuid));
    }

    /** @test */
    public function halaman_tambah_item_dan_pindah_meja_terbuka(): void
    {
        $order = $this->sesi($this->meja('Meja 6'), 'regular', 10, 50, 50000);

        $this->get(route('order-item.edit', $order->uuid))
            ->assertSuccessful()
            ->assertSee('Tambah Baris')
            ->assertSee('template-baris', false);

        // Semua meja terpakai -> pindah meja harus memberi tahu, bukan form kosong.
        $this->get(route('order.pindah-meja', $order->uuid))
            ->assertSuccessful()
            ->assertSee('Semua meja sedang terpakai');
    }

    /** @test */
    public function riwayat_minuman_tetap_terbuka_walau_kasir_pembuatnya_dihapus(): void
    {
        $meja = $this->meja('Meja 7');
        $order = $this->sesi($meja, 'regular', 10, 50, 50000);

        $kopi = Product::create(['type' => 'drink', 'product_code' => 'KP', 'name' => 'Kopi', 'price' => 10000]);
        $order->orderItems()->create(['product_uuid' => $kopi->uuid, 'quantity' => 2, 'price' => 20000]);

        // Kasir pembuat order di-soft-delete: sebelumnya view langsung 500 karena
        // $orderItem->order->user->name tanpa null-safe.
        $this->kasir->delete();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('order-history.drinks'))
            ->assertSuccessful()
            ->assertSee('Kopi');
    }

    /** @test */
    public function struk_tetap_bisa_dicetak_setelah_produknya_dihapus(): void
    {
        $meja = $this->meja('Meja 8');
        $order = $this->sesi($meja, 'regular', 10, 50, 50000);

        $meja->delete();

        $this->post(route('print'), ['order_uuid' => $order->uuid])
            ->assertSuccessful()
            ->assertSee('Meja 8');
    }
}
