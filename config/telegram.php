<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bot dan tujuan
    |--------------------------------------------------------------------------
    |
    | Token didapat dari @BotFather. Chat ID adalah tujuan pesan: bisa chat
    | pribadi admin, bisa juga grup. Kosongkan salah satunya untuk mematikan
    | fitur ini sepenuhnya.
    |
    */

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'chat_id' => env('TELEGRAM_CHAT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Jam kirim rekap harian
    |--------------------------------------------------------------------------
    |
    | Rekap satu hari dianggap "jatuh tempo" setelah jam ini lewat. Kalau PC
    | kasir sedang mati saat itu, rekapnya dikirim susulan begitu aplikasi hidup
    | lagi -- lihat App\Console\Commands\KirimRekapHarian.
    |
    */

    'report_hour' => (int) env('TELEGRAM_REPORT_HOUR', 22),

    /*
    | Batas hari yang dikirim susulan sekaligus, supaya libur panjang tidak
    | membanjiri chat dengan puluhan pesan.
    */

    'max_backlog_days' => (int) env('TELEGRAM_MAX_BACKLOG_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Kirim berkas backup ke Telegram
    |--------------------------------------------------------------------------
    |
    | Opsional, mati secara bawaan. Dump database berisi SELURUH data transaksi
    | dan hash password pengguna, sedangkan chat Telegram biasa tidak terenkripsi
    | ujung-ke-ujung. Nyalakan hanya kalau chat tujuannya memang privat.
    |
    */

    'send_backup' => (bool) env('TELEGRAM_SEND_BACKUP', false),

    'timeout' => 15,
];
