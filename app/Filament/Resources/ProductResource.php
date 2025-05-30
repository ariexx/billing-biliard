<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Hour;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cash';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->autofocus()
                    ->placeholder('Enter a name')
                    ->rules(['required', 'string'])
                    ->helperText('The name of the product.'),
                Forms\Components\TextInput::make('product_code')
                    ->required()
                    ->placeholder('Enter a product code')
                    ->rules(['required', 'string'])
                    ->helperText('The product code of the product.'),
                Forms\Components\Select::make('type')
                    ->required()
                    ->placeholder('Select a type')
                    ->options([
                        'drink' => 'Drink',
                        'snack' => 'Snack',
                        'billiard' => 'Billiard',
                        'other' => 'Other',
                    ])
                    ->reactive()
                    ->afterStateUpdated(function (\Closure $set, $state) {
                        if ($state === 'billiard') {
                            $set('price', 0);
                        }
                    })
                    ->rules(['required', 'in:drink,snack,food,billiard,other'])
                    ->helperText('Jika tipe billiard maka harga akan di set 0.'),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->dehydrated()
                    ->placeholder('Enter a price')
                    ->rules(['required', 'numeric'])
                    ->helperText('The price of the product.'),
                //add hour to product with price
                Forms\Components\Select::make('hours')
                    ->multiple()
                    ->relationship('hours', 'hour')
                    ->label('Hours')
                    ->placeholder('Select hours')
                    ->options(Hour::orderBy('hour', 'asc')->get()->pluck('name', 'uuid'))
                    ->rules(['array', 'accepted_if:type,billiard'])
                    ->helperText('Jika tipe billiard, pilih jam yang tersedia.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('product_code')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->sortable()
                    ->searchable(),
                //sort tag column by ascending
                Tables\Columns\TagsColumn::make('hours.name')
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
            'index' => Pages\ManageProducts::route('/'),
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
