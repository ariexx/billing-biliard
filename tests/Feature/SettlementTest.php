<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private Product $meja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($this->kasir);
        $this->meja = Product::create(['type' => 'billiard', 'product_code' => 'M1', 'name' => 'Meja 1', 'price' => 0]);
    }

    private function orderDenganSesi(bool $sesiAktif): Order
    {
        $order = Order::create([
            'payment_uuid' => Payment::create(['name' => 'Cash', 'is_active' => true])->uuid,
        ]);

        $sesi = $order->activeOrders()->create([
            'product_uuid' => $this->meja->uuid,
            'hour' => 1,
            'is_active' => $sesiAktif,
            'started_at' => now()->subHour(),
            'end_at' => now()->addHour(),
            'hour_type' => 'regular',
        ]);

        $order->orderItems()->create([
            'product_uuid' => $this->meja->uuid,
            'quantity' => 1,
            'price' => 50000,
            'hour' => 1,
            'active_order_unique_id' => $sesi->unique_id,
        ]);

        return $order;
    }

    /** @test */
    public function order_bisa_ditandai_lunas_dengan_metode_bayar_pilihan_kasir(): void
    {
        $order = $this->orderDenganSesi(sesiAktif: false);
        $qris = Payment::create(['name' => 'QRIS', 'is_active' => true]);

        $this->assertFalse($order->is_paid);

        $this->post(route('order.bayar', $order->uuid), ['payment_uuid' => $qris->uuid])
            ->assertRedirect(route('order.view', $order->uuid));

        $order->refresh();

        $this->assertTrue($order->is_paid);
        $this->assertSame('QRIS', $order->payment->name);
        $this->assertSame((string) $this->kasir->uuid, (string) $order->paid_by_uuid);
    }

    /** @test */
    public function order_tidak_bisa_dilunasi_saat_meja_masih_berjalan(): void
    {
        $order = $this->orderDenganSesi(sesiAktif: true);

        $this->post(route('order.bayar', $order->uuid), ['payment_uuid' => $order->payment_uuid])
            ->assertRedirect();

        $this->assertFalse($order->fresh()->is_paid);
    }

    /** @test */
    public function order_yang_sudah_lunas_tidak_bisa_dilunasi_dua_kali(): void
    {
        $order = $this->orderDenganSesi(sesiAktif: false);
        $order->update(['paid_at' => now()->subHour(), 'paid_by_uuid' => $this->kasir->uuid]);
        $waktuLunas = $order->fresh()->paid_at;

        $this->post(route('order.bayar', $order->uuid), ['payment_uuid' => $order->payment_uuid]);

        $this->assertEquals($waktuLunas, $order->fresh()->paid_at);
    }

    /** @test */
    public function tagihan_berjalan_main_bebas_dihitung_dari_tarif_per_menit(): void
    {
        $order = Order::create([
            'payment_uuid' => Payment::create(['name' => 'Cash', 'is_active' => true])->uuid,
        ]);

        $sesi = $order->activeOrders()->create([
            'product_uuid' => $this->meja->uuid,
            'hour' => 1,
            'is_active' => true,
            'started_at' => now()->subMinutes(90),
            'end_at' => now()->addHour(),
            'hour_type' => 'free time',
        ]);

        $order->orderItems()->create([
            'product_uuid' => $this->meja->uuid,
            'quantity' => 1,
            'price' => 500, // tarif per menit
            'hour' => 1,
            'active_order_unique_id' => $sesi->unique_id,
        ]);

        $this->assertSame(90, $sesi->fresh()->menit_berjalan);
        $this->assertSame(45000, $sesi->fresh()->tagihan_berjalan);
    }

    /** @test */
    public function route_order_edit_yang_rusak_sudah_dihapus(): void
    {
        // View-nya memanggil route('order.update') yang tidak pernah didaftarkan.
        $this->assertFalse(\Route::has('order.edit'));
    }
}
