<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Folder backup lokal
    |--------------------------------------------------------------------------
    |
    | Relatif terhadap storage/app. Di sinilah file .sql.gz ditulis sebelum
    | diunggah ke Google Drive.
    |
    */

    'local_path' => 'backups',

    /*
    |--------------------------------------------------------------------------
    | Masa simpan
    |--------------------------------------------------------------------------
    |
    | Backup lokal disimpan lebih pendek karena hanya berfungsi sebagai buffer;
    | salinan jangka panjang ada di Google Drive.
    |
    */

    'keep_local_days' => (int) env('BACKUP_KEEP_LOCAL_DAYS', 7),
    'keep_cloud_days' => (int) env('BACKUP_KEEP_CLOUD_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Jadwal
    |--------------------------------------------------------------------------
    |
    | Jam (0-23) saat backup otomatis dijalankan. Sengaja dipilih jam operasional
    | karena PC kasir dimatikan di luar jam buka, sehingga jadwal dini hari tidak
    | akan pernah jalan.
    |
    */

    'schedule_hours' => array_map(
        'intval',
        array_filter(explode(',', (string) env('BACKUP_SCHEDULE_HOURS', '13,21')), 'strlen')
    ),

];
