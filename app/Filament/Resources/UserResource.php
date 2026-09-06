<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;

/**
 * Sebelum resource ini ada, satu-satunya cara menambah kasir adalah lewat
 * DatabaseSeeder atau tinker -- pendaftaran mandiri dimatikan di routes/web.php
 * dan kolom role tidak bisa diubah dari UI mana pun.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pengguna';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            Forms\Components\Select::make('role')
                ->required()
                ->options([
                    'admin' => 'Admin',
                    'cashier' => 'Kasir',
                ])
                ->default('cashier')
                ->helperText('Hanya admin yang bisa membuka panel ini.'),
            Forms\Components\TextInput::make('password')
                ->password()
                ->label('Password')
                ->required(fn (string $context) => $context === 'create')
                ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                // Field kosong saat edit berarti "jangan ubah password".
                ->dehydrated(fn ($state) => filled($state))
                ->helperText('Kosongkan kalau tidak ingin mengubah password.')
                ->minLength(8),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('email')->sortable()->searchable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->label('Role')
                    ->colors(['success' => 'admin', 'primary' => 'cashier'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')->options([
                    'admin' => 'Admin',
                    'cashier' => 'Kasir',
                ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // Akun sendiri tidak boleh dihapus supaya panel tidak terkunci.
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (User $record) => $record->uuid === auth()->id()),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
