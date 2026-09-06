<?php

namespace App\Services;

/**
 * Penulis berkas .env.
 *
 * Dipakai bersama oleh wizard pemasangan dan halaman Pengaturan di panel admin.
 * Logika sehalus ini (mempertahankan key asing, mengutip nilai berspasi, tidak
 * menimpa APP_KEY) tidak boleh digandakan di dua tempat.
 */
class EnvWriter
{
    public function __construct(private ?string $path = null)
    {
    }

    public function path(): string
    {
        return $this->path ?? base_path('.env');
    }

    public function usePath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function exists(): bool
    {
        return file_exists($this->path());
    }

    public function writable(): bool
    {
        return $this->exists()
            ? is_writable($this->path())
            : is_writable(dirname($this->path()));
    }

    /**
     * Menulis sekumpulan key. Key yang tidak disebut di sini dipertahankan apa
     * adanya, termasuk yang tidak dikenal aplikasi.
     */
    public function write(array $nilai, bool $cadangkan = true): void
    {
        $path = $this->path();

        if ($this->exists()) {
            if ($cadangkan) {
                // Pemasangan ulang maupun simpan dari panel tidak boleh menghapus
                // konfigurasi lama tanpa jejak.
                copy($path, $path.'.backup-'.now()->format('Ymd-His'));
            }

            $isi = file_get_contents($path);
        } else {
            $isi = file_get_contents(base_path('.env.example'));
        }

        foreach ($nilai as $key => $value) {
            $isi = $this->setKey($isi, $key, (string) $value);
        }

        file_put_contents($path, $isi);
    }

    /**
     * Membuat APP_KEY kalau belum ada. Menggantinya akan membuat seluruh session
     * dan cookie lama tidak bisa didekripsi, jadi yang sudah terisi dibiarkan.
     */
    public function ensureAppKey(): void
    {
        $isi = $this->exists()
            ? file_get_contents($this->path())
            : file_get_contents(base_path('.env.example'));

        if ($this->hasAppKey($isi)) {
            return;
        }

        file_put_contents(
            $this->path(),
            $this->setKey($isi, 'APP_KEY', 'base64:'.base64_encode(random_bytes(32)))
        );
    }

    /**
     * Nilainya diperiksa setelah trim, BUKAN dengan /^APP_KEY=.+$/m. Berkas .env
     * di Windows berakhiran CRLF, sehingga baris kosong "APP_KEY=" menjadi
     * "APP_KEY=" + CR -- dan `.` cocok dengan CR, membuat kunci kosong dikira
     * sudah terisi sehingga aplikasi tidak pernah mendapat APP_KEY.
     */
    public function hasAppKey(?string $isi = null): bool
    {
        $isi ??= $this->exists() ? file_get_contents($this->path()) : '';

        preg_match('/^APP_KEY=(.*)$/m', $isi, $cocok);

        return isset($cocok[1]) && trim($cocok[1]) !== '';
    }

    private function setKey(string $isi, string $key, string $value): string
    {
        // Nilai dengan spasi atau karakter khusus harus dikutip agar terbaca utuh.
        $quoted = preg_match('/[\s#"\']/', $value) ? '"'.str_replace('"', '\"', $value).'"' : $value;
        $baris = $key.'='.$quoted;

        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $isi)) {
            return preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $baris, $isi, 1);
        }

        return rtrim($isi, "\n")."\n".$baris."\n";
    }
}
