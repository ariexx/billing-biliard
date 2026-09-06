<?php

namespace App\Filament\Pages;

use App\Services\EnvWriter;
use App\Services\TelegramNotifier;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Pengaturan backup, Google Drive, Telegram, dan printer dari panel admin.
 *
 * Semua nilai ini sebelumnya hanya ada di .env, jadi mengubahnya berarti
 * menyunting berkas di PC kasir -- dan gampang terlupakan saat pemasangan.
 *
 * Perubahan langsung berlaku: config tidak di-cache, dan `schedule:work`
 * menjalankan `schedule:run` sebagai proses baru tiap menit sehingga jadwal
 * backup ikut membaca .env yang baru tanpa perlu restart.
 */
class Pengaturan extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog';

    protected static ?string $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static ?string $title = 'Pengaturan';

    protected static string $view = 'filament.pages.pengaturan';

    public array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function mount(): void
    {
        // Rahasia sengaja TIDAK dimuat ke form. Yang ditampilkan hanya status
        // terisi/belum, dan field dibiarkan kosong berarti "jangan diubah".
        $this->form->fill([
            'backup_schedule_hours' => config('backup.schedule_hours')
                ? implode(',', config('backup.schedule_hours'))
                : '13,21',
            'backup_keep_local_days' => config('backup.keep_local_days'),
            'backup_keep_cloud_days' => config('backup.keep_cloud_days'),
            'gdrive_folder_id' => config('filesystems.disks.gdrive.folderId'),
            'telegram_chat_id' => config('telegram.chat_id'),
            'telegram_report_hour' => config('telegram.report_hour'),
            'telegram_send_backup' => (bool) config('telegram.send_backup'),
            'printer_name' => config('receiptprinter.connector_descriptor'),
            'paper_size' => config('receiptprinter.paper_size', '80mm'),
        ]);
    }

    private function status(?string $nilai): string
    {
        return filled($nilai)
            ? 'Sudah terisi. Kosongkan kolom ini kalau tidak ingin mengubahnya.'
            : 'BELUM DIISI.';
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Backup')
                ->description('Backup selalu tersimpan di PC. Unggahan ke Google Drive bersifat tambahan.')
                ->schema([
                    Forms\Components\TextInput::make('backup_schedule_hours')
                        ->label('Jam backup otomatis')
                        ->required()
                        ->rule('regex:/^\s*\d{1,2}(\s*,\s*\d{1,2})*\s*$/')
                        ->helperText('Pisahkan dengan koma, contoh: 13,21. Pilih jam saat toko buka, karena PC mati di luar itu.'),
                    Forms\Components\TextInput::make('backup_keep_local_days')
                        ->label('Simpan di PC (hari)')
                        ->numeric()->minValue(1)->maxValue(365)->required(),
                    Forms\Components\TextInput::make('backup_keep_cloud_days')
                        ->label('Simpan di Google Drive (hari)')
                        ->numeric()->minValue(1)->maxValue(3650)->required(),
                ])->columns(3),

            Forms\Components\Section::make('Google Drive')
                ->description('Kosongkan kalau belum punya. Backup tetap tersimpan di PC.')
                ->schema([
                    Forms\Components\TextInput::make('gdrive_client_id')
                        ->label('Client ID')->password()
                        ->helperText($this->status(config('filesystems.disks.gdrive.clientId'))),
                    Forms\Components\TextInput::make('gdrive_client_secret')
                        ->label('Client Secret')->password()
                        ->helperText($this->status(config('filesystems.disks.gdrive.clientSecret'))),
                    Forms\Components\TextInput::make('gdrive_refresh_token')
                        ->label('Refresh Token')->password()
                        ->helperText($this->status(config('filesystems.disks.gdrive.refreshToken'))
                            .' Dibuat lewat: php artisan gdrive:authorize'),
                    Forms\Components\TextInput::make('gdrive_folder_id')
                        ->label('Folder ID')
                        ->helperText('Ambil dari URL folder Drive. Kosongkan untuk menyimpan di root My Drive.'),
                ])->columns(2),

            Forms\Components\Section::make('Rekap Telegram')
                ->description('Rekap harian dikirim otomatis. Hari yang terlewat dikirim susulan.')
                ->schema([
                    Forms\Components\TextInput::make('telegram_bot_token')
                        ->label('Bot Token')->password()
                        ->helperText($this->status(config('telegram.bot_token')).' Dibuat lewat @BotFather.'),
                    Forms\Components\TextInput::make('telegram_chat_id')
                        ->label('Chat ID')
                        ->helperText('Dapat dari @userinfobot. Untuk grup, pakai ID grupnya.'),
                    Forms\Components\TextInput::make('telegram_report_hour')
                        ->label('Jam kirim rekap')
                        ->numeric()->minValue(0)->maxValue(23)->required(),
                    Forms\Components\Toggle::make('telegram_send_backup')
                        ->label('Kirim berkas backup ke Telegram')
                        ->helperText('Berkas backup berisi seluruh data transaksi dan hash password. '
                            .'Chat Telegram biasa tidak terenkripsi ujung-ke-ujung; nyalakan hanya untuk chat privat.'),
                ])->columns(2),

            Forms\Components\Section::make('Printer')
                ->schema([
                    Forms\Components\TextInput::make('printer_name')
                        ->label('Nama printer Windows')
                        ->helperText('Sama persis seperti di Windows > Settings > Printers & scanners.'),
                    Forms\Components\Select::make('paper_size')
                        ->label('Ukuran kertas')
                        ->options(['80mm' => '80mm', '58mm' => '58mm'])
                        ->required(),
                ])->columns(2),
        ];
    }

    public function simpan(): void
    {
        $data = $this->form->getState();
        $writer = app(EnvWriter::class);

        if (! $writer->writable()) {
            Notification::make()
                ->title('Berkas .env tidak bisa ditulis')
                ->body('Periksa izin berkas '.$writer->path())
                ->danger()->send();

            return;
        }

        $nilai = [
            'BACKUP_SCHEDULE_HOURS' => preg_replace('/\s+/', '', $data['backup_schedule_hours']),
            'BACKUP_KEEP_LOCAL_DAYS' => (int) $data['backup_keep_local_days'],
            'BACKUP_KEEP_CLOUD_DAYS' => (int) $data['backup_keep_cloud_days'],
            'GOOGLE_DRIVE_FOLDER_ID' => $data['gdrive_folder_id'] ?? '',
            'TELEGRAM_CHAT_ID' => $data['telegram_chat_id'] ?? '',
            'TELEGRAM_REPORT_HOUR' => (int) $data['telegram_report_hour'],
            'TELEGRAM_SEND_BACKUP' => $data['telegram_send_backup'] ? 'true' : 'false',
            'PRINTER' => $data['printer_name'] ?? '',
            'RECEIPTPRINTER_PAPER_SIZE' => $data['paper_size'],
        ];

        // Rahasia hanya ditulis kalau memang diisi. Kolom kosong berarti "biarkan
        // nilai yang sudah ada", supaya menyimpan form tidak menghapus token yang
        // sengaja tidak ditampilkan.
        foreach ([
            'gdrive_client_id' => 'GOOGLE_DRIVE_CLIENT_ID',
            'gdrive_client_secret' => 'GOOGLE_DRIVE_CLIENT_SECRET',
            'gdrive_refresh_token' => 'GOOGLE_DRIVE_REFRESH_TOKEN',
            'telegram_bot_token' => 'TELEGRAM_BOT_TOKEN',
        ] as $field => $key) {
            if (filled($data[$field] ?? null)) {
                $nilai[$key] = $data[$field];
            }
        }

        $writer->write($nilai);
        Artisan::call('config:clear');

        Notification::make()
            ->title('Pengaturan disimpan')
            ->body('Perubahan langsung berlaku. Salinan .env sebelumnya ikut disimpan.')
            ->success()->send();

        $this->redirect(static::getUrl());
    }

    protected function getActions(): array
    {
        return [
            \Filament\Pages\Actions\Action::make('backupSekarang')
                ->label('Backup Sekarang')
                ->icon('heroicon-o-download')
                ->requiresConfirmation()
                ->action('backupSekarang'),

            \Filament\Pages\Actions\Action::make('tesDrive')
                ->label('Tes Google Drive')
                ->icon('heroicon-o-cloud-upload')
                ->color('secondary')
                ->action('tesDrive'),

            \Filament\Pages\Actions\Action::make('tesTelegram')
                ->label('Tes Telegram')
                ->icon('heroicon-o-paper-airplane')
                ->color('secondary')
                ->action('tesTelegram'),
        ];
    }

    public function backupSekarang(): void
    {
        $kode = Artisan::call('backup:database');
        $keluaran = trim(Artisan::output());

        Notification::make()
            ->title($kode === 0 ? 'Backup selesai' : 'Backup gagal')
            ->body(\Illuminate\Support\Str::limit($keluaran, 300))
            ->status($kode === 0 ? 'success' : 'danger')
            ->send();
    }

    public function tesDrive(): void
    {
        try {
            // Menulis lalu menghapus berkas kecil: membuktikan kredensialnya benar
            // DAN foldernya bisa ditulis, bukan sekadar bisa dihubungi.
            $nama = 'uji-koneksi-'.now()->format('Ymd-His').'.txt';
            Storage::disk('gdrive')->put($nama, 'uji koneksi dari panel admin');
            Storage::disk('gdrive')->delete($nama);

            Notification::make()->title('Google Drive terhubung')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Google Drive gagal')
                ->body(\Illuminate\Support\Str::limit($e->getMessage(), 300))
                ->danger()->send();
        }
    }

    public function tesTelegram(): void
    {
        try {
            app(TelegramNotifier::class)->kirim(
                '<b>Uji koneksi</b>'.PHP_EOL
                .TelegramNotifier::escape(config('app.name')).' — '.now()->format('d/m/Y H:i')
            );

            Notification::make()->title('Pesan uji terkirim ke Telegram')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Telegram gagal')
                ->body(\Illuminate\Support\Str::limit($e->getMessage(), 300))
                ->danger()->send();
        }
    }

    /**
     * Daftar backup yang tersimpan di PC, terbaru dulu.
     */
    public function getBackupLokalProperty(): array
    {
        $dir = storage_path('app/'.config('backup.local_path'));
        $berkas = glob($dir.DIRECTORY_SEPARATOR.'*.sql.gz') ?: [];

        usort($berkas, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return array_map(fn ($f) => [
            'nama' => basename($f),
            'ukuran' => round(filesize($f) / 1048576, 2),
            'waktu' => \Illuminate\Support\Carbon::createFromTimestamp(filemtime($f)),
        ], array_slice($berkas, 0, 10));
    }
}
