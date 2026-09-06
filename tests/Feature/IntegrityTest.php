<?php

namespace Tests\Feature;

use App\Models\ActiveOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        return Order::create([
            'payment_uuid' => Payment::create(['name' => 'Cash', 'is_active' => true])->uuid,
        ]);
    }

    /** @test */
    public function nomor_order_berurutan_dan_tidak_pernah_kembar(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier']));

        $nomor = collect(range(1, 25))->map(fn () => $this->order()->order_number);

        $this->assertSame($nomor->unique()->count(), $nomor->count());
        $this->assertSame('ORD-'.date('Ymd').'-0001', $nomor->first());
        $this->assertSame('ORD-'.date('Ymd').'-0025', $nomor->last());
    }

    /** @test */
    public function order_number_ditolak_database_kalau_kembar(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier']));

        $pertama = $this->order();

        $this->expectException(\Illuminate\Database\QueryException::class);

        // UNIQUE index adalah pengaman terakhir kalau generator pernah bocor.
        Order::create([
            'payment_uuid' => $pertama->payment_uuid,
            'order_number' => $pertama->order_number,
        ]);
    }

    /** @test */
    public function blok_reguler_kadaluarsa_ditutup_tanpa_perlu_browser_terbuka(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier']));

        $meja = Product::create(['type' => 'billiard', 'product_code' => 'M1', 'name' => 'Meja 1', 'price' => 0]);
        $order = $this->order();

        $lewat = $order->activeOrders()->create([
            'product_uuid' => $meja->uuid,
            'hour' => 1,
            'is_active' => true,
            'started_at' => now()->subHours(2),
            'end_at' => now()->subHour(),
            'hour_type' => 'regular',
        ]);

        $berjalan = $order->activeOrders()->create([
            'product_uuid' => $meja->uuid,
            'hour' => 2,
            'is_active' => true,
            'started_at' => now(),
            'end_at' => now()->addHours(2),
            'hour_type' => 'regular',
        ]);

        $bebas = $order->activeOrders()->create([
            'product_uuid' => $meja->uuid,
            'hour' => 1,
            'is_active' => true,
            'started_at' => now()->subHours(5),
            'end_at' => now()->subHours(4),
            'hour_type' => 'free time',
        ]);

        $this->artisan('orders:expire-sessions')->assertSuccessful();

        $this->assertFalse((bool) $lewat->fresh()->is_active);
        $this->assertTrue((bool) $berjalan->fresh()->is_active);
        // Main bebas tidak boleh ditutup otomatis: tagihannya belum dihitung.
        $this->assertTrue((bool) $bebas->fresh()->is_active);
    }

    /** @test */
    public function pemeriksa_integritas_lolos_pada_database_bersih(): void
    {
        $this->artisan('db:integrity-check')->assertSuccessful();
    }
}
