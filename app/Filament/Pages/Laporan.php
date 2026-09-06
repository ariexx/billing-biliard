<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * Laporan rentang tanggal.
 *
 * Widget dashboard terkunci di "hari ini" dan "7 hari", jadi sebelum ini
 * pertanyaan "bulan lalu dapat berapa" hanya bisa dijawab dengan mengexport data
 * mentah lalu mengolahnya di Excel.
 */
class Laporan extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan';

    protected static string $view = 'filament.pages.laporan';

    public ?string $dari = null;

    public ?string $sampai = null;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function mount(): void
    {
        $this->form->fill([
            'dari' => today()->startOfMonth()->toDateString(),
            'sampai' => today()->toDateString(),
        ]);
    }

    /**
     * Tombol uji: memastikan token dan chat ID benar tanpa menunggu jadwal.
     */
    protected function getActions(): array
    {
        return [
            \Filament\Pages\Actions\Action::make('kirimTelegram')
                ->label('Kirim rekap ke Telegram')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalSubheading(fn () => 'Mengirim rekap tanggal '
                    .Carbon::parse($this->sampai ?: today())->format('d/m/Y').'.')
                ->action('kirimKeTelegram'),
        ];
    }

    public function kirimKeTelegram(): void
    {
        $tanggal = Carbon::parse($this->sampai ?: today());

        try {
            $pesan = app(\App\Console\Commands\KirimRekapHarian::class)->susunPesan($tanggal);
            app(\App\Services\TelegramNotifier::class)->kirim($pesan);

            \Filament\Notifications\Notification::make()
                ->title('Rekap terkirim ke Telegram')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            \Filament\Notifications\Notification::make()
                ->title('Gagal mengirim')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\DatePicker::make('dari')->label('Dari tanggal')->required()->reactive(),
                Forms\Components\DatePicker::make('sampai')->label('Sampai tanggal')->required()->reactive(),
            ]),
        ];
    }

    private function rekap(): \App\Services\Rekap
    {
        // Seluruh angka berasal dari App\Services\Rekap, sumber yang sama dengan
        // rekap harian Telegram -- supaya keduanya tidak pernah berbeda.
        return new \App\Services\Rekap(
            $this->dari ?: today()->startOfMonth(),
            $this->sampai ?: today()
        );
    }

    public function getRingkasanProperty(): array
    {
        return $this->rekap()->ringkasan();
    }

    public function getPerHariProperty()
    {
        return $this->rekap()->perHari();
    }

    public function getPerMetodeBayarProperty()
    {
        return $this->rekap()->perMetodeBayar();
    }

    public function getPerKasirProperty()
    {
        return $this->rekap()->perKasir();
    }

    public function getProdukTerlarisProperty()
    {
        return $this->rekap()->produkTerlaris();
    }

    public function getPemanfaatanMejaProperty()
    {
        return $this->rekap()->pemanfaatanMeja();
    }

    public function getJamTersibukProperty()
    {
        return $this->rekap()->jamTersibuk();
    }
}
