<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Ifsnop\Mysqldump\Mysqldump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database
                            {--keep-local= : Simpan backup lokal berapa hari (default dari config)}
                            {--keep-cloud= : Simpan backup di Google Drive berapa hari (default dari config)}
                            {--no-upload : Hanya dump ke lokal, jangan upload ke cloud}';

    protected $description = 'Dump database ke .sql.gz, upload ke Google Drive, lalu bersihkan backup lama';

    /**
     * mysqldump-php dipakai (bukan binary mysqldump) karena mesin kasir Windows
     * tidak punya mysqldump di PATH. Lihat CLAUDE.md.
     */
    public function handle(): int
    {
        $startedAt = microtime(true);

        try {
            $path = $this->dump();
        } catch (\Throwable $e) {
            return $this->fail('Dump database gagal', $e);
        }

        $sizeMb = round(filesize($path) / 1048576, 2);
        $this->info("Dump selesai: {$path} ({$sizeMb} MB)");

        $uploaded = false;
        if (! $this->option('no-upload')) {
            try {
                $uploaded = $this->upload($path);
            } catch (\Throwable $e) {
                // Dump lokal sudah aman, jadi upload gagal bukan alasan untuk exit non-zero
                // tanpa pesan yang jelas — tapi tetap dilaporkan sebagai kegagalan.
                return $this->fail('Upload ke Google Drive gagal (dump lokal tetap tersimpan di '.$path.')', $e);
            }
        }

        $this->prune();

        $duration = round(microtime(true) - $startedAt, 1);
        $summary = sprintf(
            'Backup sukses: %s (%s MB) - upload: %s - durasi: %ss',
            basename($path),
            $sizeMb,
            $uploaded ? 'ya' : 'tidak',
            $duration
        );

        $this->info($summary);
        \Log::channel('daily')->info($summary);

        return self::SUCCESS;
    }

    /**
     * protected supaya test bisa mengganti langkah dump dengan file tiruan dan
     * menguji jalur upload + prune tanpa perlu server MySQL.
     */
    protected function dump(): string
    {
        $dir = storage_path('app/'.config('backup.local_path'));

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Tidak bisa membuat folder backup: {$dir}");
        }

        $connection = config('database.default');
        $db = config("database.connections.{$connection}");

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $db['driver'],
            $db['host'],
            $db['port'],
            $db['database'],
            $db['charset'] ?? 'utf8mb4'
        );

        $filename = sprintf('%s-%s.sql.gz', $db['database'], now()->format('Ymd-His'));
        $path = $dir.DIRECTORY_SEPARATOR.$filename;

        $dumper = new Mysqldump($dsn, $db['username'], $db['password'], [
            'compress' => Mysqldump::GZIP,
            'add-drop-table' => true,
            'single-transaction' => true,
            'lock-tables' => false,
            'default-character-set' => Mysqldump::UTF8MB4,
        ]);

        $dumper->start($path);

        return $path;
    }

    private function upload(string $path): bool
    {
        $disk = Storage::disk('gdrive');
        $stream = fopen($path, 'rb');

        try {
            $disk->writeStream(basename($path), $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->info('Upload ke Google Drive selesai: '.basename($path));

        return true;
    }

    private function prune(): void
    {
        $keepLocal = (int) ($this->option('keep-local') ?? config('backup.keep_local_days'));
        $cutoff = Carbon::now()->subDays($keepLocal);
        $dir = storage_path('app/'.config('backup.local_path'));

        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.sql.gz') ?: [] as $file) {
            if (Carbon::createFromTimestamp(filemtime($file))->lt($cutoff)) {
                unlink($file);
                $this->line('Hapus backup lokal lama: '.basename($file));
            }
        }

        if ($this->option('no-upload')) {
            return;
        }

        $keepCloud = (int) ($this->option('keep-cloud') ?? config('backup.keep_cloud_days'));
        $cloudCutoff = Carbon::now()->subDays($keepCloud);

        try {
            $disk = Storage::disk('gdrive');

            foreach ($disk->files() as $file) {
                if (! str_ends_with($file, '.sql.gz')) {
                    continue;
                }

                if (Carbon::createFromTimestamp($disk->lastModified($file))->lt($cloudCutoff)) {
                    $disk->delete($file);
                    $this->line('Hapus backup cloud lama: '.$file);
                }
            }
        } catch (\Throwable $e) {
            // Pembersihan cloud yang gagal tidak boleh menggagalkan backup yang sudah sukses.
            $this->warn('Gagal membersihkan backup lama di Google Drive: '.$e->getMessage());
        }
    }

    private function fail(string $context, \Throwable $e): int
    {
        $message = $context.': '.$e->getMessage();

        $this->error($message);
        \Log::channel('daily')->error('Backup GAGAL - '.$message);

        return self::FAILURE;
    }
}
