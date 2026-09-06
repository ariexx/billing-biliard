<?php

namespace Tests\Feature;

use App\Models\ActiveOrder;
use App\Models\Hour;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Menutup tiga bug tagihan yang paling merugikan sebelum diperbaiki.
 */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function kasir(): User
    {
        return User::factory()->create(['role' => 'cashier']);
    }

    private function meja(string $name = 'Meja 1'): Product
    {
        return Product::create([
            'type' => 'billiard',
            'product_code' => 'MJ-'.$name,
            'name' => $name,
            'price' => 0,
        ]);
    }

    private function sesiMainBebas(User $kasir, Product $meja, int $tarifPerMenit, int $menitLalu): array
    {
        $this->actingAs($kasir);

        $order = Order::create(['payment_uuid' => Payment::create(['name' => 'Cash', 'is_active' => true])->uuid]);

        $session = $order->activeOrders()->create([
            'product_uuid' => $meja->uuid,
            'hour' => 1,
            'is_active' => true,
            'started_at' => now()->subMinutes($menitLalu),
            'end_at' => now()->addHour(),
            'hour_type' => 'free time',
        ]);

        $item = $order->orderItems()->create([
            'product_uuid' => $meja->uuid,
            'quantity' => 1,
            'price' => $tarifPerMenit,
            'hour' => 1,
            'active_order_unique_id' => $session->unique_id,
        ]);

        return [$order, $session, $item];
    }

    /** @test */
    public function selesai_yang_diklik_dua_kali_tidak_menggandakan_tagihan(): void
    {
        $kasir = $this->kasir();
        [$order, $session, $item] = $this->sesiMainBebas($kasir, $this->meja(), 500, 120);

        Livewire::actingAs($kasir)
            ->test(\App\Http\Livewire\ActiveOrder::class)
            ->call('stopTimer', $order->uuid, $session->unique_id)
            ->call('stopTimer', $order->uuid, $session->unique_id);

        // 120 menit x Rp 500 = Rp 60.000, dan klik kedua tidak boleh mengubahnya.
        $this->assertSame(60000, $item->fresh()->price);
        $this->assertFalse((bool) $session->fresh()->is_active);
    }

    /** @test */
    public function sesi_main_bebas_tidak_bisa_ditutup_lewat_habiskan_tanpa_ditagih(): void
    {
        $kasir = $this->kasir();
        [$order, $session, $item] = $this->sesiMainBebas($kasir, $this->meja(), 500, 120);

        Livewire::actingAs($kasir)
            ->test(\App\Http\Livewire\ActiveOrder::class)
            ->call('habiskanWaktu', $session->unique_id);

        // Sesi harus tetap terbuka: tarif per menit belum berubah jadi total.
        $this->assertTrue((bool) $session->fresh()->is_active);
        $this->assertSame(500, $item->fresh()->price);
    }

    /** @test */
    public function jumlah_pesanan_menempel_pada_produk_yang_benar(): void
    {
        $kasir = $this->kasir();
        [$order] = $this->sesiMainBebas($kasir, $this->meja(), 500, 10);

        $kopi = Product::create(['type' => 'drink', 'product_code' => 'KP', 'name' => 'Kopi', 'price' => 10000]);
        $aqua = Product::create(['type' => 'drink', 'product_code' => 'AQ', 'name' => 'Aqua', 'price' => 5000]);

        $this->actingAs($kasir)
            ->put(route('order-item.update', $order->uuid), [
                'product' => [$kopi->uuid, $aqua->uuid],
                'quantity' => [1, 10],
            ])
            ->assertRedirect(route('order.view', $order->uuid));

        $this->assertSame(1, (int) OrderItem::where('product_uuid', $kopi->uuid)->value('quantity'));
        $this->assertSame(10, (int) OrderItem::where('product_uuid', $aqua->uuid)->value('quantity'));
        $this->assertSame(50000, (int) OrderItem::where('product_uuid', $aqua->uuid)->value('price'));
    }

    /** @test */
    public function baris_produk_yang_sama_dijumlahkan_bukan_hilang(): void
    {
        $kasir = $this->kasir();
        [$order] = $this->sesiMainBebas($kasir, $this->meja(), 500, 10);

        $kopi = Product::create(['type' => 'drink', 'product_code' => 'KP', 'name' => 'Kopi', 'price' => 10000]);

        $this->actingAs($kasir)->put(route('order-item.update', $order->uuid), [
            'product' => [$kopi->uuid, $kopi->uuid],
            'quantity' => [2, 3],
        ]);

        $items = OrderItem::where('product_uuid', $kopi->uuid)->get();
        $this->assertCount(1, $items);
        $this->assertSame(5, (int) $items->first()->quantity);
    }

    /** @test */
    public function perpanjangan_blok_reguler_yang_sudah_lewat_dihitung_dari_sekarang(): void
    {
        $kasir = $this->kasir();
        $this->actingAs($kasir);

        $meja = $this->meja();
        $order = Order::create(['payment_uuid' => Payment::create(['name' => 'Cash', 'is_active' => true])->uuid]);

        $session = $order->activeOrders()->create([
            'product_uuid' => $meja->uuid,
            'hour' => 1,
            'is_active' => true,
            'started_at' => now()->subHours(3),
            'end_at' => now()->subHours(2), // blok sudah lewat dua jam lalu
            'hour_type' => 'regular',
        ]);

        $hour = Hour::create(['name' => '1 Jam', 'hour' => 1, 'type' => 'regular', 'price' => 50000]);

        $this->put(route('order-item.update', $order->uuid), ['hour' => $hour->uuid]);

        // Perpanjangan harus jatuh di masa depan, bukan menumpuk di end_at lama.
        $this->assertTrue($session->fresh()->end_at->isFuture());
    }

    /** @test */
    public function kasir_tidak_bisa_menyentuh_order_kasir_lain(): void
    {
        $kasirA = $this->kasir();
        [$order, , $item] = $this->sesiMainBebas($kasirA, $this->meja(), 500, 10);

        $kasirB = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($kasirB)->get(route('order.view', $order->uuid))->assertForbidden();
        $this->actingAs($kasirB)->delete(route('order-item.destroy', $item->uuid))->assertForbidden();
        $this->actingAs($kasirB)->post(route('print'), ['order_uuid' => $order->uuid])->assertForbidden();
    }

    /** @test */
    public function order_gagal_dengan_pesan_jelas_saat_belum_ada_metode_pembayaran(): void
    {
        $kasir = $this->kasir();
        $meja = $this->meja();
        $hour = Hour::create(['name' => '1 Jam', 'hour' => 1, 'type' => 'regular', 'price' => 50000]);

        Livewire::actingAs($kasir)
            ->test(\App\Http\Livewire\Product::class)
            ->set('selectedHours', [$meja->uuid => $hour->uuid])
            ->call('saveOrder', $meja->uuid);

        // Tanpa perbaikan, baris Payment::first()->uuid melempar Error sebelum
        // guard-nya sempat jalan.
        $this->assertSame(0, Order::count());
    }
}
