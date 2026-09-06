<?php

namespace App\Filament\Resources;

use App\Exports\OrdersExport;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Order';

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
                        Forms\Components\Select::make('product_uuid')->label('Product')
                            ->options(fn() => Product::all()->pluck('name', 'uuid'))
                            ->reactive()
                            ->afterStateUpdated(function (\Closure $set, $state) {
                                // Harga diisikan sebagai saran; kasir/admin tetap
                                // bisa mengubahnya. Versi lama menyimpan harga di
                                // session() -- $set() mengembalikan null, jadi yang
                                // tersimpan selalu null dan bocor antar request.
                                $product = Product::with('hours')->whereUuid($state)->first();

                                if (! $product) {
                                    return;
                                }

                                $set('price', $product->hours->first()?->price ?? $product->price);
                            })
                            ->required()
                            ->rules(['required', 'string', 'exists:products,uuid']),
                        Forms\Components\TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->rules(['required', 'integer', 'min:1']),
                        // Field 'total' dihapus: order_items tidak punya kolom total.
                        Forms\Components\TextInput::make('price')
                            ->label('Harga (total baris)')
                            ->numeric()
                            ->required()
                            ->rules(['required', 'integer', 'min:0']),
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
                Tables\Columns\TextColumn::make('payment.name')->label('Metode Bayar')->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($record) => rupiah((int) $record->orderItems->sum('price'))),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Sebelumnya TrashedFilter adalah satu-satunya filter di seluruh
                // panel, sehingga laporan per tanggal atau per kasir mustahil.
                Tables\Filters\Filter::make('rentang_tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('dari')->label('Dari tanggal'),
                        Forms\Components\DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                            ->when($data['sampai'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (blank($data['dari'] ?? null) && blank($data['sampai'] ?? null)) {
                            return null;
                        }

                        return 'Tanggal: '.($data['dari'] ?? '...').' s/d '.($data['sampai'] ?? '...');
                    }),
                Tables\Filters\SelectFilter::make('user_uuid')
                    ->label('Kasir')
                    ->relationship('user', 'name'),
                Tables\Filters\SelectFilter::make('payment_uuid')
                    ->label('Metode Bayar')
                    ->relationship('payment', 'name'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Rincian'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('export')
                    ->label('Export Excel')
                    ->icon('heroicon-o-download')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => Excel::download(
                        new OrdersExport($records),
                        'orders-'.now()->format('Ymd-His').'.xlsx'
                    )),
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
