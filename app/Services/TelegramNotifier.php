<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramNotifier
{
    public function aktif(): bool
    {
        return filled(config('telegram.bot_token')) && filled(config('telegram.chat_id'));
    }

    /**
     * Karakter yang punya arti khusus di parse_mode HTML milik Telegram.
     * Nama produk dan nama kasir berasal dari input pengguna, jadi wajib lolos
     * fungsi ini sebelum ditempel ke pesan.
     */
    public static function escape(?string $teks): string
    {
        return htmlspecialchars((string) $teks, ENT_NOQUOTES, 'UTF-8');
    }

    /**
     * @throws \RuntimeException kalau Telegram menolak atau tidak bisa dihubungi
     */
    public function kirim(string $pesan): void
    {
        if (! $this->aktif()) {
            throw new \RuntimeException('Telegram belum dikonfigurasi: TELEGRAM_BOT_TOKEN dan TELEGRAM_CHAT_ID harus diisi.');
        }

        $response = Http::timeout(config('telegram.timeout', 15))
            ->asForm()
            ->post('https://api.telegram.org/bot'.config('telegram.bot_token').'/sendMessage', [
                'chat_id' => config('telegram.chat_id'),
                'text' => $pesan,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

        if ($response->failed() || $response->json('ok') !== true) {
            // Token TIDAK ikut dicatat: pesan error Telegram bisa masuk log.
            throw new \RuntimeException(
                'Telegram menolak pesan: '.($response->json('description') ?? 'HTTP '.$response->status())
            );
        }
    }

    /** Batas unggah Bot API adalah 50 MB; disisakan margin. */
    public const BATAS_DOKUMEN_MB = 45;

    /**
     * Mengirim berkas (dipakai untuk backup database).
     *
     * @throws \RuntimeException kalau berkas terlalu besar, Telegram menolak,
     *                           atau tidak bisa dihubungi
     */
    public function kirimDokumen(string $path, ?string $caption = null): void
    {
        if (! $this->aktif()) {
            throw new \RuntimeException('Telegram belum dikonfigurasi.');
        }

        if (! is_file($path)) {
            throw new \RuntimeException('Berkas tidak ditemukan: '.$path);
        }

        $ukuranMb = filesize($path) / 1048576;

        if ($ukuranMb > self::BATAS_DOKUMEN_MB) {
            throw new \RuntimeException(sprintf(
                'Berkas %.1f MB melebihi batas unggah Telegram (%d MB).',
                $ukuranMb,
                self::BATAS_DOKUMEN_MB
            ));
        }

        $response = Http::timeout(config('telegram.timeout', 15) * 6)
            ->attach('document', file_get_contents($path), basename($path))
            ->post('https://api.telegram.org/bot'.config('telegram.bot_token').'/sendDocument', array_filter([
                'chat_id' => config('telegram.chat_id'),
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]));

        if ($response->failed() || $response->json('ok') !== true) {
            throw new \RuntimeException(
                'Telegram menolak berkas: '.($response->json('description') ?? 'HTTP '.$response->status())
            );
        }
    }
}
