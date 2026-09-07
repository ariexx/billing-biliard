<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * laravel-datatables-vite menyetel dom global yang memuat 'B', sehingga tombol
 * Excel/CSV/PDF/Print muncul sendiri tanpa didaftarkan di kode.
 *
 * Pada tabel serverSide tombol itu hanya mengekspor baris yang sedang termuat di
 * halaman, bukan seluruh periode yang difilter -- laporan uang yang diam-diam
 * tidak lengkap. Karena itu dom dipatok tanpa 'B', dan export dikerjakan server.
 */
class DataTableDomTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function riwayat_order_tidak_merender_tombol_export_sisi_klien(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier']));

        $html = $this->get(route('order-history'))->assertSuccessful()->getContent();

        // Skrip DataTables memuat dom yang dipatok, dan dom itu tanpa 'B'.
        $this->assertMatchesRegularExpression('/"dom":\s*"[^"]*"/', $html);

        preg_match('/"dom":\s*"([^"]*)"/', $html, $cocok);
        $dom = $cocok[1] ?? '';

        $this->assertNotEmpty($dom, 'dom harus dipatok, bukan mengandalkan default paket');
        $this->assertStringNotContainsString('B', $dom, 'dom tidak boleh memuat tombol Buttons');

        // Bagian yang tetap dibutuhkan: panjang halaman, pencarian, tabel, info, paging.
        foreach (['l', 'f', 't', 'i', 'p'] as $bagian) {
            $this->assertStringContainsString($bagian, $dom);
        }
    }

    /** @test */
    public function export_tetap_tersedia_lewat_tombol_server(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier']));

        // Penggantinya harus benar-benar ada di halaman, bukan sekadar dihapus.
        $this->get(route('order-history'))
            ->assertSuccessful()
            ->assertSee(route('order-history.export'))
            ->assertSee('Export Excel');
    }
}
