<?php

namespace Tests\Feature;

use App\Filament\Pages\Pengaturan;
use App\Models\User;
use App\Services\EnvWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PengaturanTest extends TestCase
{
    use RefreshDatabase;

    private string $envUji;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        // .env tiruan: halaman ini menulis berkas sungguhan, jadi .env mesin
        // pengembang tidak boleh tersentuh oleh test.
        $this->envUji = tempnam(sys_get_temp_dir(), 'envset');
        file_put_contents($this->envUji, implode(PHP_EOL, [
            'APP_KEY=base64:KUNCILAMA=',
            'GOOGLE_DRIVE_CLIENT_ID=id-lama',
            'GOOGLE_DRIVE_CLIENT_SECRET=rahasia-lama',
            'TELEGRAM_BOT_TOKEN=token-lama',
            'KEY_ASING=jangan-hilang',
        ]).PHP_EOL);

        $this->app->bind(EnvWriter::class, fn () => (new EnvWriter())->usePath($this->envUji));
    }

    protected function tearDown(): void
    {
        @unlink($this->envUji);
        foreach (glob($this->envUji.'.backup-*') ?: [] as $b) {
            @unlink($b);
        }

        parent::tearDown();
    }

    private function isiEnv(): string
    {
        return file_get_contents($this->envUji);
    }

    private function halaman()
    {
        return Livewire::test(Pengaturan::class);
    }

    /** @test */
    public function halaman_terbuka_dan_menampilkan_semua_bagian(): void
    {
        $this->halaman()
            ->assertSuccessful()
            ->assertSee('Backup')
            ->assertSee('Google Drive')
            ->assertSee('Rekap Telegram')
            ->assertSee('Printer');
    }

    /** @test */
    public function kasir_tidak_melihat_menu_pengaturan(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier']));

        $this->assertFalse(Pengaturan::shouldRegisterNavigation());
    }

    /** @test */
    public function rahasia_tidak_pernah_dikirim_ke_browser(): void
    {
        // Token hanya ditandai "sudah terisi"; nilainya tidak boleh ikut dirender.
        $this->halaman()
            ->assertSuccessful()
            ->assertDontSee('token-lama')
            ->assertDontSee('rahasia-lama')
            ->assertDontSee('id-lama');
    }

    /** @test */
    public function menyimpan_dengan_kolom_rahasia_kosong_tidak_menghapus_nilai_lama(): void
    {
        // Ini perilaku paling berbahaya kalau salah: admin membuka halaman,
        // mengubah jam backup, menyimpan -- dan token Telegram ikut terhapus.
        $this->halaman()
            ->fillForm([
                'backup_schedule_hours' => '10,20',
                'backup_keep_local_days' => 7,
                'backup_keep_cloud_days' => 30,
                'telegram_report_hour' => 21,
                'telegram_send_backup' => false,
                'paper_size' => '80mm',
            ])
            ->call('simpan');

        $isi = $this->isiEnv();

        $this->assertStringContainsString('TELEGRAM_BOT_TOKEN=token-lama', $isi);
        $this->assertStringContainsString('GOOGLE_DRIVE_CLIENT_SECRET=rahasia-lama', $isi);
        $this->assertStringContainsString('GOOGLE_DRIVE_CLIENT_ID=id-lama', $isi);
        $this->assertStringContainsString('BACKUP_SCHEDULE_HOURS=10,20', $isi);
    }

    /** @test */
    public function rahasia_yang_diisi_menimpa_nilai_lama(): void
    {
        $this->halaman()
            ->fillForm([
                'backup_schedule_hours' => '13,21',
                'backup_keep_local_days' => 7,
                'backup_keep_cloud_days' => 30,
                'telegram_bot_token' => 'token-baru',
                'telegram_report_hour' => 22,
                'telegram_send_backup' => true,
                'paper_size' => '80mm',
            ])
            ->call('simpan');

        $isi = $this->isiEnv();

        $this->assertStringContainsString('TELEGRAM_BOT_TOKEN=token-baru', $isi);
        $this->assertStringContainsString('TELEGRAM_SEND_BACKUP=true', $isi);
    }

    /** @test */
    public function key_di_luar_form_dan_app_key_tetap_utuh(): void
    {
        $this->halaman()
            ->fillForm([
                'backup_schedule_hours' => '13,21',
                'backup_keep_local_days' => 7,
                'backup_keep_cloud_days' => 30,
                'telegram_report_hour' => 22,
                'paper_size' => '58mm',
            ])
            ->call('simpan');

        $isi = $this->isiEnv();

        $this->assertStringContainsString('KEY_ASING=jangan-hilang', $isi);
        // Mengganti APP_KEY membuat seluruh session dan cookie lama tidak terbaca.
        $this->assertStringContainsString('APP_KEY=base64:KUNCILAMA=', $isi);
        $this->assertStringContainsString('RECEIPTPRINTER_PAPER_SIZE=58mm', $isi);
    }

    /** @test */
    public function jam_backup_ngawur_ditolak(): void
    {
        $this->halaman()
            ->fillForm([
                'backup_schedule_hours' => 'pagi dan sore',
                'backup_keep_local_days' => 7,
                'backup_keep_cloud_days' => 30,
                'telegram_report_hour' => 22,
                'paper_size' => '80mm',
            ])
            ->call('simpan')
            ->assertHasFormErrors(['backup_schedule_hours']);

        // Berkas tidak boleh berubah sama sekali kalau validasinya gagal.
        $this->assertStringNotContainsString('pagi dan sore', $this->isiEnv());
    }

    /** @test */
    public function menyimpan_membuat_salinan_cadangan_env(): void
    {
        $this->halaman()
            ->fillForm([
                'backup_schedule_hours' => '9,17',
                'backup_keep_local_days' => 7,
                'backup_keep_cloud_days' => 30,
                'telegram_report_hour' => 22,
                'paper_size' => '80mm',
            ])
            ->call('simpan');

        $this->assertNotEmpty(glob($this->envUji.'.backup-*'));
    }

    /** @test */
    public function tes_telegram_melapor_gagal_kalau_belum_dikonfigurasi(): void
    {
        config(['telegram.bot_token' => null, 'telegram.chat_id' => null]);

        // Tidak boleh melempar exception ke layar; harus jadi notifikasi.
        $this->halaman()->call('tesTelegram')->assertSuccessful();
    }

    /** @test */
    public function tes_google_drive_melapor_gagal_kalau_belum_dikonfigurasi(): void
    {
        $this->halaman()->call('tesDrive')->assertSuccessful();
    }
}
