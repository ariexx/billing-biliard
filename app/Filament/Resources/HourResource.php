<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HourResource\Pages;
use App\Filament\Resources\HourResource\RelationManagers;
use App\Models\Hour;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HourResource extends Resource
{
    protected static ?string $model = Hour::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Paket Jam';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->rules(['required', 'string']),
                Forms\Components\TextInput::make('hour')
                    ->required()
                    ->rules(['required', 'numeric']),
                Forms\Components\Select::make('type')
                    ->required()
                    ->reactive()
                    ->options([
                        'free time' => 'Main bebas (dihitung per menit)',
                        'regular' => 'Reguler (blok jam, bayar di muka)',
                    ])
                    ->rules(['in:free time,regular']),
                /*
                 * Satuan harga BERBEDA per tipe, dan salah isi di sini langsung
                 * jadi salah tagih: stopTimer() mengalikan harga ini dengan jumlah
                 * MENIT yang dimainkan untuk sesi main bebas.
                 */
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->label(fn (\Closure $get) => $get('type') === 'free time'
                        ? 'Tarif per MENIT (Rp)'
                        : 'Harga paket (Rp)')
                    ->helperText(fn (\Closure $get) => $get('type') === 'free time'
                        ? 'Main bebas ditagih per menit. Contoh: isi 500 berarti Rp 500/menit (Rp 30.000/jam).'
                        : 'Harga sekali bayar untuk seluruh blok jam ini.')
                    ->rules(['required', 'numeric', 'min:0']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('hour')
                    ->sortable()
                    ->default(true),
                Tables\Columns\TextColumn::make('type')
                    ->sortable()
                    ->default(true),
                Tables\Columns\TextColumn::make('price')
                    ->sortable()
                    ->default(true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageHours::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
