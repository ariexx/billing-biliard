<?php

namespace App\Services;

use App\Models\Hour;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pemasangan sekali jalan untuk PC kasir baru.
 *
 * Semua langkah dijalankan dalam satu request supaya tidak ada state wizard yang
 * perlu disimpan di session -- penting, karena sebelum .env terisi aplikasi
 * belum tentu punya APP_KEY untuk mengenkripsi session.
 */
class Installer
{
    /** Bisa diarahkan ke file lain oleh test supaya .env asli tidak tersentuh. */
    private ?string $envPath = null;

    public function useEnvPath(string $path): static
    {
        $this->envPath = $path;

        return $this;
    }

    public function envPath(): string
    {
        return $this->envPath ?? base_path('.env');
    }

    public function markerPath(): string
    {
        return storage_path('installed');
    }

    public function isInstalled(): bool
    {
        return file_exists($this->markerPath());
    }

    /**
     * Syarat sistem. Ditampilkan di langkah pertama wizard.
     *
     * @return array<int, array{nama: string, wajib: bool, terpenuhi: bool, nilai: string}>
     */
    public function requirements(): array
    {
        $ext = fn (string $name) => extension_loaded($name);

        return [
            ['nama' => 'PHP >= 8.0.2', 'wajib' => true, 'terpenuhi' => version_compare(PHP_VERSION, '8.0.2', '>='), 'nilai' => PHP_VERSION],
            ['nama' => 'Ekstensi pdo_mysql', 'wajib' => true, 'terpenuhi' => $ext('pdo_mysql'), 'nilai' => $ext('pdo_mysql') ? 'aktif' : 'tidak aktif'],
            ['nama' => 'Ekstensi mbstring', 'wajib' => true, 'terpenuhi' => $ext('mbstring'), 'nilai' => $ext('mbstring') ? 'aktif' : 'tidak aktif'],
            ['nama' => 'Ekstensi openssl', 'wajib' => true, 'terpenuhi' => $ext('openssl'), 'nilai' => $ext('openssl') ? 'aktif' : 'tidak aktif'],
            ['nama' => 'Ekstensi zip (untuk backup)', 'wajib' => false, 'terpenuhi' => $ext('zip'), 'nilai' => $ext('zip') ? 'aktif' : 'tidak aktif'],
            ['nama' => 'Ekstensi curl (untuk Google Drive)', 'wajib' => false, 'terpenuhi' => $ext('curl'), 'nilai' => $ext('curl') ? 'aktif' : 'tidak aktif'],
            ['nama' => 'Folder storage bisa ditulis', 'wajib' => true, 'terpenuhi' => is_writable(storage_path()), 'nilai' => is_writable(storage_path()) ? 'ya' : 'tidak'],
            ['nama' => 'File .env bisa ditulis', 'wajib' => true, 'terpenuhi' => $this->envWritable(), 'nilai' => $this->envWritable() ? 'ya' : 'tidak'],
        ];
    }

    public function requirementsMet(): bool
    {
        return collect($this->requirements())
            ->filter(fn ($r) => $r['wajib'])
            ->every(fn ($r) => $r['terpenuhi']);
    }

    private function envWritable(): bool
    {
        return file_exists(base_path('.env'))
            ? is_writable(base_path('.env'))
            : is_writable(base_path());
    }

    /**
     * Uji koneksi database tanpa menyentuh .env, dipakai tombol "Tes Koneksi".
     */
    public function testDatabase(array $db): array
    {
        config([
            'database.connections.instal_uji' => array_merge(
                config('database.connections.mysql'),
                [
                    'host' => $db['host'],
                    'port' => $db['port'],
                    'database' => $db['database'],
                    'username' => $db['username'],
                    'password' => $db['password'] ?? '',
                ]
            ),
        ]);

        DB::purge('instal_uji');

        try {
            DB::connection('instal_uji')->getPdo();

            $jumlahTabel = count(DB::connection('instal_uji')->select('SHOW TABLES'));

            return [
                'ok' => true,
                'pesan' => $jumlahTabel === 0
                    ? 'Koneksi berhasil. Database masih kosong, siap dipasang.'
                    : "Koneksi berhasil. Database sudah berisi {$jumlahTabel} tabel; migrasi hanya akan menambah yang belum ada.",
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'pesan' => $this->pesanKoneksi($e)];
        } finally {
            DB::purge('instal_uji');
        }
    }

    private function pesanKoneksi(\Throwable $e): string
    {
        $pesan = $e->getMessage();

        return match (true) {
            str_contains($pesan, 'Unknown database') => 'Database tidak ditemukan. Buat dulu databasenya, atau periksa namanya.',
            str_contains($pesan, 'Access denied') => 'Username atau password database salah.',
            str_contains($pesan, 'Connection refused'), str_contains($pesan, 'No connection') => 'Server MySQL tidak merespon. Pastikan MySQL sudah berjalan.',
            default => 'Gagal terhubung: '.Str::limit($pesan, 200),
        };
    }

    /**
     * Menjalankan seluruh pemasangan. Dipanggil sekali dari InstallController.
     */
    public function install(array $data): void
    {
        $this->writeEnv($data);
        $this->applyRuntimeConfig($data);

        Artisan::call('migrate', ['--force' => true]);

        DB::transaction(function () use ($data) {
            $this->createAdmin($data);
            $this->createMasterData($data);
        });

        Artisan::call('config:clear');
        Artisan::call('view:clear');

        file_put_contents(
            $this->markerPath(),
            'Dipasang pada '.now()->format('Y-m-d H:i:s').' oleh '.$data['admin_email'].PHP_EOL
        );
    }

    /**
     * Menulis .env berbasis file yang sudah ada (atau .env.example kalau belum),
     * sehingga key yang tidak dikenal wizard tetap dipertahankan.
     */
    public function writeEnv(array $data): void
    {
        $path = $this->envPath();

        if (file_exists($path)) {
            // Salinan cadangan: pemasangan ulang tidak boleh menghapus konfigurasi lama diam-diam.
            copy($path, $path.'.backup-'.now()->format('Ymd-His'));
            $isi = file_get_contents($path);
        } else {
            $isi = file_get_contents(base_path('.env.example'));
        }

        $nilai = [
            'APP_NAME' => $data['app_name'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $data['app_url'],
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'] ?? '',
            'PRINTER' => $data['printer_name'] ?? '',
            'RECEIPTPRINTER_PAPER_SIZE' => $data['paper_size'] ?? '80mm',
            'BACKUP_SCHEDULE_HOURS' => $data['backup_hours'] ?? '13,21',
            'GOOGLE_DRIVE_CLIENT_ID' => $data['gdrive_client_id'] ?? '',
            'GOOGLE_DRIVE_CLIENT_SECRET' => $data['gdrive_client_secret'] ?? '',
            'GOOGLE_DRIVE_REFRESH_TOKEN' => $data['gdrive_refresh_token'] ?? '',
            'GOOGLE_DRIVE_FOLDER_ID' => $data['gdrive_folder_id'] ?? '',
            'TELEGRAM_BOT_TOKEN' => $data['telegram_bot_token'] ?? '',
            'TELEGRAM_CHAT_ID' => $data['telegram_chat_id'] ?? '',
            'TELEGRAM_REPORT_HOUR' => (string) ($data['telegram_report_hour'] ?? 22),
            'TELEGRAM_SEND_BACKUP' => ! empty($data['telegram_send_backup']) ? 'true' : 'false',
        ];

        foreach ($nilai as $key => $value) {
            $isi = $this->setEnvKey($isi, $key, $value);
        }

        // APP_KEY hanya dibuat kalau belum ada: menggantinya akan membuat seluruh
        // session dan cookie lama tidak bisa didekripsi.
        if (! preg_match('/^APP_KEY=.+$/m', $isi)) {
            $isi = $this->setEnvKey($isi, 'APP_KEY', 'base64:'.base64_encode(random_bytes(32)));
        }

        file_put_contents($path, $isi);
    }

    private function setEnvKey(string $isi, string $key, string $value): string
    {
        // Nilai dengan spasi atau karakter khusus harus dikutip agar terbaca utuh.
        $quoted = preg_match('/[\s#"\']/', $value) ? '"'.str_replace('"', '\"', $value).'"' : $value;
        $baris = $key.'='.$quoted;

        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $isi)) {
            return preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $baris, $isi, 1);
        }

        return rtrim($isi, "\n")."\n".$baris."\n";
    }

    /**
     * .env yang baru ditulis tidak terbaca oleh proses yang sedang berjalan,
     * jadi koneksi database untuk migrasi diarahkan lewat config runtime.
     */
    private function applyRuntimeConfig(array $data): void
    {
        config([
            'database.connections.mysql.host' => $data['db_host'],
            'database.connections.mysql.port' => $data['db_port'],
            'database.connections.mysql.database' => $data['db_database'],
            'database.connections.mysql.username' => $data['db_username'],
            'database.connections.mysql.password' => $data['db_password'] ?? '',
            'database.default' => 'mysql',
        ]);

        DB::purge('mysql');
    }

    private function createAdmin(array $data): void
    {
        User::updateOrCreate(
            ['email' => $data['admin_email']],
            [
                'name' => $data['admin_name'],
                'password' => Hash::make($data['admin_password']),
                'role' => 'admin',
            ]
        );

        if (! empty($data['cashier_email'])) {
            User::updateOrCreate(
                ['email' => $data['cashier_email']],
                [
                    'name' => $data['cashier_name'] ?: 'Kasir',
                    'password' => Hash::make($data['cashier_password']),
                    'role' => 'cashier',
                ]
            );
        }
    }

    private function createMasterData(array $data): void
    {
        $metode = collect(explode(',', $data['payment_methods'] ?? 'Tunai'))
            ->map(fn ($n) => trim($n))
            ->filter()
            ->unique();

        foreach ($metode as $nama) {
            Payment::firstOrCreate(['name' => $nama], ['is_active' => true]);
        }

        $paketJam = collect($data['hours'] ?? [])
            ->filter(fn ($h) => filled($h['name'] ?? null))
            ->map(fn ($h) => Hour::firstOrCreate(
                ['name' => $h['name']],
                [
                    'hour' => (int) $h['hour'],
                    'type' => $h['type'],
                    // Untuk 'free time' angka ini adalah tarif PER MENIT, lihat
                    // ActiveOrder::stopTimer().
                    'price' => (int) $h['price'],
                ]
            ));

        $jumlahMeja = (int) ($data['table_count'] ?? 0);

        for ($i = 1; $i <= $jumlahMeja; $i++) {
            $nama = trim(($data['table_prefix'] ?? 'Meja').' '.$i);

            $meja = Product::firstOrCreate(
                ['product_code' => 'MEJA-'.$i],
                ['name' => $nama, 'type' => 'billiard', 'price' => 0]
            );

            // Tanpa relasi ini, dropdown durasi di kasir kosong dan meja tidak
            // bisa dipakai sama sekali.
            $meja->hours()->syncWithoutDetaching($paketJam->pluck('uuid')->all());
        }
    }
}
