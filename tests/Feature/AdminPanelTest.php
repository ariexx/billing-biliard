<?php

namespace Tests\Feature;

use App\Models\ActiveOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function dataContoh(): void
    {
        $meja = Product::create(['type' => 'billiard', 'product_code' => 'M1', 'name' => 'Meja 1', 'price' => 0]);
        $order = Order::create(['payment_uuid' => Payment::create(['name' => 'Cash', 'is_active' => true])->uuid]);

        $sesi = $order->activeOrders()->create([
            'product_uuid' => $meja->uuid,
            'hour' => 1,
            'is_active' => true,
            'started_at' => now(),
            'end_at' => now()->addHour(),
            'hour_type' => 'regular',
        ]);

        $order->orderItems()->create([
            'product_uuid' => $meja->uuid,
            'quantity' => 1,
            'price' => 50000,
            'hour' => 1,
            'active_order_unique_id' => $sesi->unique_id,
        ]);
    }

    /** @test */
    public function kasir_tidak_bisa_membuka_panel_admin(): void
    {
        $kasir = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($kasir)->get('/billiard-admin')->assertForbidden();
    }

    /** @test */
    public function dashboard_admin_menampilkan_widget_tanpa_error(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->dataContoh();

        $this->get('/billiard-admin')->assertSuccessful();

        Livewire::test(\App\Filament\Widgets\RingkasanHariIni::class)
            ->assertSuccessful()
            ->assertSee('Omzet Hari Ini')
            ->assertSee('Meja Terpakai');

        Livewire::test(\App\Filament\Widgets\OmzetTujuhHari::class)->assertSuccessful();

        Livewire::test(\App\Filament\Widgets\MejaAktif::class)
            ->assertSuccessful()
            ->assertSee('Meja 1');
    }

    /** @test */
    public function admin_bisa_membuat_akun_kasir_dengan_password_terhash(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(\App\Filament\Resources\UserResource\Pages\ManageUsers::class)
            ->callPageAction('create', data: [
                'name' => 'Kasir Baru',
                'email' => 'kasirbaru@contoh.test',
                'role' => 'cashier',
                'password' => 'rahasia123',
            ])
            ->assertHasNoPageActionErrors();

        $baru = User::where('email', 'kasirbaru@contoh.test')->first();

        $this->assertNotNull($baru);
        $this->assertSame('cashier', $baru->role);
        $this->assertNotSame('rahasia123', $baru->password);
        $this->assertTrue(\Hash::check('rahasia123', $baru->password));
    }

    /** @test */
    public function halaman_order_admin_terbuka_dan_menampilkan_total_rupiah(): void
    {
        $this->actingAs($this->admin());
        $this->dataContoh();

        Livewire::test(\App\Filament\Resources\OrderResource\Pages\ManageOrders::class)
            ->assertSuccessful()
            ->assertSee('Rp 50.000,00');
    }
}
