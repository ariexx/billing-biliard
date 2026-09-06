<?php

namespace Tests\Feature;

use App\Console\Commands\BackupDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Menguji jalur upload dan prune tanpa perlu server MySQL maupun kredensial
 * Google Drive: langkah dump diganti file tiruan, disk gdrive dipalsukan.
 */
class BackupTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('app/'.config('backup.local_path'));

        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }

        foreach (glob($this->dir.DIRECTORY_SEPARATOR.'*.sql.gz') ?: [] as $file) {
            unlink($file);
        }

        Storage::fake('gdrive');

        // Perintah tiruan menimpa backup:database: langkah dump menulis file gz
        // kecil, sisanya (upload, prune, logging) tetap kode asli.
        $this->app[\Illuminate\Contracts\Console\Kernel::class]
            ->registerCommand(new FakeBackupDatabase());
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.DIRECTORY_SEPARATOR.'*.sql.gz') ?: [] as $file) {
            unlink($file);
        }

        parent::tearDown();
    }

    private function backupLokal(): array
    {
        return glob($this->dir.DIRECTORY_SEPARATOR.'*.sql.gz') ?: [];
    }

    /** @test */
    public function backup_diunggah_ke_google_drive(): void
    {
        $this->artisan('backup:database')->assertSuccessful();

        $this->assertCount(1, $this->backupLokal());

        $diDrive = Storage::disk('gdrive')->files();
        $this->assertCount(1, $diDrive);
        $this->assertStringEndsWith('.sql.gz', $diDrive[0]);
    }

    /** @test */
    public function opsi_no_upload_hanya_menyimpan_lokal(): void
    {
        $this->artisan('backup:database', ['--no-upload' => true])->assertSuccessful();

        $this->assertCount(1, $this->backupLokal());
        $this->assertCount(0, Storage::disk('gdrive')->files());
    }

    /** @test */
    public function backup_lokal_yang_kedaluwarsa_dihapus(): void
    {
        $lama = $this->dir.DIRECTORY_SEPARATOR.'billiard-lama.sql.gz';
        file_put_contents($lama, 'lama');
        touch($lama, now()->subDays(30)->timestamp);

        $baru = $this->dir.DIRECTORY_SEPARATOR.'billiard-baru.sql.gz';
        file_put_contents($baru, 'baru');

        $this->artisan('backup:database', ['--keep-local' => 7])->assertSuccessful();

        $this->assertFileDoesNotExist($lama);
        $this->assertFileExists($baru);
    }

    /** @test */
    public function kegagalan_upload_dilaporkan_sebagai_gagal_bukan_sukses_diam_diam(): void
    {
        // Meniru Drive tidak bisa dihubungi: disk gdrive gagal diresolusi.
        Storage::forgetDisk('gdrive');
        config(['filesystems.disks.gdrive.driver' => 'driver-tidak-ada']);

        $this->artisan('backup:database')->assertFailed();

        // Dump lokal tetap tersimpan supaya tidak kehilangan salinannya.
        $this->assertCount(1, $this->backupLokal());
    }
}

class FakeBackupDatabase extends BackupDatabase
{
    protected function dump(): string
    {
        $dir = storage_path('app/'.config('backup.local_path'));

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.DIRECTORY_SEPARATOR.'billiard_test-'.now()->format('Ymd-His').'.sql.gz';
        file_put_contents($path, gzencode('-- dump tiruan untuk test'));

        return $path;
    }
}
