<?php

namespace Tests\Feature;

use App\Models\Hour;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kasir tidak boleh bisa menghapus tagihan atas waktu yang sudah dimainkan.
 */
class VoidItemTest extends TestCase
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

    /**
     * @return array{0: Order, 1: OrderItem, 2: OrderItem} order, baris waktu, baris minuman
     */
    private function orderLengkap(string $tipe = 'regular'): array
    {
        $order = Order::create([
            'payment_uuid' => Payment::create(['name' => 'Tunai', 'is_active' => true])->uuid,
        ]);

        $sesi = $order->activeOrders()->create([
            'product_uuid' => $this->meja->uuid,
            'hour' => 1,
            'is_active' => false,
            'started_at' => now()->subMinutes(150),
            'end_at' => now(),
            'hour_type' => $tipe,
        ]);

        $barisWaktu = $order->orderItems()->create([
            'product_uuid' => $this->meja->uuid,
            'quantity' => 1,
            'price' => 75000,
            'hour' => 1,
            'active_order_unique_id' => $sesi->unique_id,
        ]);

        $kopi = Product::create(['type' => 'drink', 'product_code' => 'KP', 'name' => 'Kopi', 'price' => 10000]);
        $barisMinuman = $order->orderItems()->create([
            'product_uuid' => $kopi->uuid,
            'quantity' => 1,
            'price' => 10000,
        ]);

        return [$order, $barisWaktu, $barisMinuman];
    }

    /** @test */
    public function kasir_tidak_bisa_membatalkan_baris_waktu_meja(): void
    {
        [$order, $barisWaktu] = $this->orderLengkap();

        $this->delete(route('order-item.destroy', $barisWaktu->uuid), ['void_reason' => 'iseng'])
            ->assertForbidden();

        // Tagihannya harus tetap utuh.
        $this->assertNotNull($barisWaktu->fresh());
        $this->assertSame(85000, (int) $order->fresh()->total);
    }

    /** @test */
    public function tombol_batal_baris_waktu_tidak_dirender_untuk_kasir(): void
    {
        [$order, $barisWaktu, $barisMinuman] = $this->orderLengkap();

        $html = $this->get(route('order.view', $order->uuid))->assertSuccessful()->getContent();

        $this->assertStringNotContainsString(route('order-item.destroy', $barisWaktu->uuid), $html);
        $this->assertStringContainsString(route('order-item.destroy', $barisMinuman->uuid), $html);
    }

    /** @test */
    public function admin_boleh_membatalkan_baris_waktu_dengan_alasan(): void
    {
        [$order, $barisWaktu] = $this->orderLengkap();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->delete(route('order-item.destroy', $barisWaktu->uuid), ['void_reason' => 'salah meja'])
            ->assertRedirect();

        // find() menerapkan SoftDeletingScope; fresh() tidak, jadi fresh() tetap
        // mengembalikan baris yang sudah di-soft-delete.
        $this->assertNull(OrderItem::find($barisWaktu->uuid));
        $this->assertSame('salah meja', OrderItem::withTrashed()->find($barisWaktu->uuid)->void_reason);
    }

    /** @test */
    public function kasir_boleh_membatalkan_minuman_tapi_wajib_beralasan(): void
    {
        [$order, , $barisMinuman] = $this->orderLengkap();

        $this->delete(route('order-item.destroy', $barisMinuman->uuid), [])
            ->assertSessionHasErrors('void_reason');
        $this->assertNotNull($barisMinuman->fresh());

        $this->delete(route('order-item.destroy', $barisMinuman->uuid), ['void_reason' => 'salah input'])
            ->assertRedirect();
        $this->assertNull(OrderItem::find($barisMinuman->uuid));
    }

    /** @test */
    public function pembatalan_mencatat_alasan_dan_pelakunya(): void
    {
        [, , $barisMinuman] = $this->orderLengkap();

        $this->delete(route('order-item.destroy', $barisMinuman->uuid), ['void_reason' => 'gelas pecah']);

        $batal = OrderItem::withTrashed()->find($barisMinuman->uuid);

        $this->assertSame('gelas pecah', $batal->void_reason);
        $this->assertSame((string) $this->kasir->uuid, (string) $batal->voided_by_uuid);
    }

    /** @test */
    public function item_yang_dibatalkan_tetap_terlihat_di_halaman_order(): void
    {
        [$order, , $barisMinuman] = $this->orderLengkap();

        $this->delete(route('order-item.destroy', $barisMinuman->uuid), ['void_reason' => 'salah input']);

        $this->get(route('order.view', $order->uuid))
            ->assertSuccessful()
            ->assertSee('Item dibatalkan')
            ->assertSee('salah input');
    }

    /** @test */
    public function order_yang_sudah_lunas_itemnya_tidak_bisa_dibatalkan(): void
    {
        [$order, , $barisMinuman] = $this->orderLengkap();
        $order->update(['paid_at' => now(), 'paid_by_uuid' => $this->kasir->uuid]);

        $this->delete(route('order-item.destroy', $barisMinuman->uuid), ['void_reason' => 'coba-coba'])
            ->assertForbidden();

        $this->assertNotNull($barisMinuman->fresh());
    }

    /** @test */
    public function baris_main_bebas_juga_terkunci_dari_kasir(): void
    {
        [, $barisWaktu] = $this->orderLengkap('free time');

        $this->delete(route('order-item.destroy', $barisWaktu->uuid), ['void_reason' => 'iseng'])
            ->assertForbidden();
    }

    /** @test */
    public function durasi_main_bebas_menampilkan_lama_main_bukan_angka_paket_admin(): void
    {
        // Paket "Main Bebas" di admin diisi hour = 1, padahal dimainkan 150 menit.
        [$order, $barisWaktu] = $this->orderLengkap('free time');

        $this->assertSame(150, $barisWaktu->durasiMenit());
        $this->assertSame('2 jam 30 menit', $barisWaktu->labelDurasi());

        $this->get(route('order.view', $order->uuid))
            ->assertSuccessful()
            ->assertSee('2 jam 30 menit')
            ->assertDontSee('<td>1 Jam</td>', false);

        $this->post(route('print'), ['order_uuid' => $order->uuid])
            ->assertSuccessful()
            ->assertSee('2 jam 30 menit');
    }

    /** @test */
    public function durasi_blok_reguler_tetap_memakai_jam_paket(): void
    {
        [$order, $barisWaktu] = $this->orderLengkap('regular');

        $this->assertNull($barisWaktu->durasiMenit());
        $this->assertSame('1 Jam', $barisWaktu->labelDurasi());
    }

    /** @test */
    public function main_bebas_yang_masih_jalan_dihitung_sampai_sekarang(): void
    {
        $order = Order::create([
            'payment_uuid' => Payment::create(['name' => 'Tunai', 'is_active' => true])->uuid,
        ]);

        $sesi = $order->activeOrders()->create([
            'product_uuid' => $this->meja->uuid,
            'hour' => 1,
            'is_active' => true,
            'started_at' => now()->subMinutes(45),
            // end_at untuk sesi terbuka tidak bermakna; jangan dipakai.
            'end_at' => now()->addHour(),
            'hour_type' => 'free time',
        ]);

        $item = $order->orderItems()->create([
            'product_uuid' => $this->meja->uuid,
            'quantity' => 1,
            'price' => 500,
            'hour' => 1,
            'active_order_unique_id' => $sesi->unique_id,
        ]);

        $this->assertSame(45, $item->durasiMenit());
        $this->assertSame('0 jam 45 menit', $item->labelDurasi());
    }
}
