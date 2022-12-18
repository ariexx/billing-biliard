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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('hour')
                    ->required()
                    ->rules(['required', 'numeric']),
                Forms\Components\Select::make('type')
                    ->required()
                    ->options([
                        'free time' => 'Free time',
                        'regular' => 'Regular',
                    ])
                    ->rules(['in:free time,regular']),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->rules(['required', 'numeric']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
