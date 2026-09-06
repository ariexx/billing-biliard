<?php

namespace App\Console\Commands;

use Google\Client;
use Illuminate\Console\Command;

class GoogleDriveAuthorize extends Command
{
    protected $signature = 'gdrive:authorize';

    protected $description = 'Tukar izin Google Drive menjadi refresh token untuk disimpan di .env';

    public function handle(): int
    {
        $clientId = config('filesystems.disks.gdrive.clientId');
        $clientSecret = config('filesystems.disks.gdrive.clientSecret');

        if (empty($clientId) || empty($clientSecret)) {
            $this->error('GOOGLE_DRIVE_CLIENT_ID dan GOOGLE_DRIVE_CLIENT_SECRET harus diisi di .env terlebih dahulu.');
            $this->newLine();
            $this->line('Langkah membuatnya:');
            $this->line('  1. Buka https://console.cloud.google.com/ lalu buat project baru.');
            $this->line('  2. APIs & Services > Library > cari "Google Drive API" > Enable.');
            $this->line('  3. APIs & Services > OAuth consent screen > External > isi nama aplikasi.');
            $this->line('     Tambahkan email Anda sendiri sebagai Test user.');
            $this->line('  4. APIs & Services > Credentials > Create Credentials > OAuth client ID.');
            $this->line('     Application type: Desktop app.');
            $this->line('  5. Salin Client ID dan Client Secret ke .env, lalu jalankan ulang perintah ini.');

            return self::FAILURE;
        }

        $client = new Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
        $client->setScopes(['https://www.googleapis.com/auth/drive']);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $this->newLine();
        $this->line('1. Buka URL berikut di browser dan setujui aksesnya:');
        $this->newLine();
        $this->line($client->createAuthUrl());
        $this->newLine();

        $code = trim((string) $this->ask('2. Tempel kode otorisasi yang diberikan Google'));

        if ($code === '') {
            $this->error('Kode otorisasi kosong.');

            return self::FAILURE;
        }

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            $this->error('Google menolak kode tersebut: '.($token['error_description'] ?? $token['error']));

            return self::FAILURE;
        }

        if (empty($token['refresh_token'])) {
            $this->error('Google tidak mengirim refresh_token. Cabut akses aplikasi di');
            $this->error('https://myaccount.google.com/permissions lalu ulangi perintah ini.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Berhasil. Tambahkan baris ini ke .env:');
        $this->newLine();
        $this->line('GOOGLE_DRIVE_REFRESH_TOKEN='.$token['refresh_token']);
        $this->newLine();
        $this->line('Opsional: isi GOOGLE_DRIVE_FOLDER_ID dengan ID folder tujuan di Drive');
        $this->line('(ambil dari URL folder: https://drive.google.com/drive/folders/<ID-INI>).');
        $this->line('Kosongkan kalau backup mau disimpan di root My Drive.');

        return self::SUCCESS;
    }
}
