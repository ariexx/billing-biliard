<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Models\Product;
use Faker\Provider\Text;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('payment_uuid')
                    ->required()
                    ->label('Payment Type')
                    ->relationship('payment', 'name', fn($query) => $query->orderBy('name'))
                    ->rules(['required', 'string', 'exists:payments,uuid'])
                    ->columnSpan(2),
                Forms\Components\Repeater::make('orderItems')
                    ->relationship('orderItems')
                    ->schema([
                        Forms\Components\Select::make('product_uuid')
                            ->options(fn() => Product::all()->pluck('name', 'uuid'))
                            ->reactive()
                            ->afterStateUpdated(function (\Closure $set, $state) {
                                $product = Product::with('hours')->whereUuid($state)->firstOrFail();
                                //check if the product has hours
                                if ($product->hours->count() > 0) {
                                    $price = $set('price', $product->hours->first()->price);
                                } else {
                                    $price = $set('price', $product->price);
                                }
                                session()->put('price', $price);
                            })
                            ->required()
                            ->rules(['required', 'string', 'exists:products,uuid']),
                        Forms\Components\TextInput::make('quantity')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (\Closure $set, $state) {
                                $totalPrice = $state * session()->get('price');
                                session('totalPrice', $totalPrice);
                                $set('price', $totalPrice);
                                $set('total', $totalPrice);
                            })
                            ->rules(['required', 'numeric']),
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->dehydrated()
                            ->disabled(),
                        //make the total price is latest
                        Forms\Components\TextInput::make('total')
                            ->numeric()
                            ->dehydrated()
                            ->disabled(),
                    ])
                    ->columnSpan(2)
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_uuid')
                    ->name('user.name')
                    ->label('Cashier')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_uuid')->label('Payment Type')->name('payment.name'),
                //format to rupiah
                Tables\Columns\TextColumn::make('total')->default(fn($record) => $record->orderItems->sum('price')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i:s'),
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
            'index' => Pages\ManageOrders::route('/'),
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
