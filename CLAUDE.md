# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Point-of-sale / billing app for a billiard hall ("Black Dragon Pool"). Cashiers open a table, a countdown runs, drinks/snacks get added to the same order, then the receipt is printed. Laravel 9 + Livewire 2 + Filament 2, MySQL, UI copy is mostly Indonesian.

## Commands

```powershell
php artisan serve            # dev server (start.bat runs exactly this)
npm run dev                  # Vite dev server (sass/app.scss + js/app.js)
npm run build                # production assets
php artisan migrate --seed   # schema + Admin/Cashier users (DatabaseSeeder)
vendor/bin/pint              # formatter (Laravel Pint)
php artisan test             # or vendor/bin/phpunit
php artisan test --filter=SomeTest
```

Tests: `phpunit.xml` leaves `DB_CONNECTION`/`:memory:` commented out, so the suite hits the **real configured MySQL database**. Uncomment those lines before writing DB-touching tests. Only the stock `ExampleTest` stubs exist today.

`.env` needs `PRINTER` (Windows printer name, e.g. `POS-80C`) and `RECEIPTPRINTER_PAPER_SIZE`.

## Two front ends

- **Cashier UI** — `/home`, Blade + Bootstrap 5 + Livewire, auth via `laravel/ui`. Registration/reset/verify routes are disabled in `routes/web.php`.
- **Admin UI** — Filament at `/billiard-admin` (`config/filament.php`, `FILAMENT_PATH`). Access is gated by `User::canAccessFilament()` → `role === 'admin'`. Resources: Product, Hour, Payment, Order. `z3d0x/filament-logger` records activity; `Gate::define('isAdmin')` also guards `/logs` (laravel-log-viewer).

Roles live in `users.role` (`admin` / `cashier`); see `OrderPolicy` and `AuthServiceProvider`.

## Domain model — read this before touching any model

Every domain model uses a **string `uuid` primary key**, not an auto-increment `id`:

```php
protected $primaryKey = 'uuid'; protected $keyType = 'string'; public $incrementing = false;
use HasUuid;   // App\Traits\HasUuid fills uuid on creating
```

Because the parent key is `uuid`, Eloquent derives foreign keys as `order_uuid`, `product_uuid`, `payment_uuid`, `user_uuid` automatically — that's why the relations declare no explicit key names. Keep this pattern for new models/migrations (`$table->foreignUuid('x_uuid')->references('uuid')->on('xs')`).

`ActiveOrder` is the exception: it extends `Pivot`, its PK is `unique_id` (a `uniqid()` assigned in `boot()`), and `OrderItem.active_order_unique_id` points at it. One `Order` has one *current* `activeOrder`, but a free-time session can create an additional row, so several `active_orders` rows can share an `order_uuid`.

Entity roles:
- `Product` — `type` enum `snack|drink|billiard|other`. Billiard products are the **tables**; their `price` is set to 0 and pricing comes from linked `Hour`s via the `hours_to_products` pivot.
- `Hour` — a sellable time package: `hour`, `price`, `name`, and `type` enum `regular | free time`.
- `ActiveOrder` — a running table session: `started_at`, `end_at`, `is_active`, `hour_type`.
- `OrderItem` — one line on the receipt (a time package *or* a drink/snack).

### Billing rules (the core logic)

`hour_type` drives everything, split across `Http\Livewire\Product::saveOrder`, `Http\Livewire\ActiveOrder`, and `OrderItemController::update`:

- **regular** — prepaid block. `end_at = now() + hour`, the order item is priced at `hour->price` up front. Adding another regular package *extends* the existing active order (`hour += `, `end_at->addHours()`), it does not create a new row.
- **free time** — open-ended play. `ActiveOrder::stopTimer()` closes it and rewrites the item price as `started_at->diffInMinutes(now()) * orderItem->price`, i.e. the stored price is a **per-minute rate** until the session ends, and a **total** afterwards.
- Guards in `OrderItemController::update`: a free-time package can't start while a regular block is still running (`end_at > now()` → "Waktu belum habis"), and a regular package can't be added on top of a live free-time session ("Main bebas belum habis").

`resources/views/livewire/active-order.blade.php` polls every 10s and, in its `@else` branch, **writes to the database from the template** (`$order->update(['is_active' => false])`) to expire finished regular blocks. There is no scheduler/queue job doing this — the dashboard has to be open. Keep that in mind before "cleaning up" that view.

### Two accessor gotchas

- `Order::getTotalAttribute()` shadows the `orders.total` column and always recomputes `orderItems->sum('price')`. Writing `total` has no visible effect.
- `Product::getTypeAttribute()` returns `ucfirst($value)`. So queries use lowercase (`where('type', 'billiard')`, `scopeBilliard`) while anything reading `$product->type` in PHP/Blade must compare against `'Billiard'` — see `order/print-receipt.blade.php`.

## Printing

`POST /print` → `PrintController` bumps `orders.print_count`, writes a line to the `daily` log channel, and returns `order/print-receipt.blade.php`, which renders through `layouts/print` — a CSS-only 80mm receipt printed from the browser. `charlieuki/receiptprinter` and `config/receiptprinter.php` (ESC/POS over the Windows `PRINTER` queue) are installed and configured but **not currently used** by the controller; recent history moved away from direct ESC/POS printing.

## Other conventions

- `rupiah(int $angka)` is a global helper autoloaded via composer `files` (`app/Helpers/rupiah.php`) — use it for all currency output.
- `AppServiceProvider::boot()` pins `date_default_timezone_set('Asia/Jakarta')` and `Carbon::setLocale('id')`.
- Order history tables are **server-side yajra DataTables** (`App\DataTables\OrdersDataTable`), rendered by `HomeController::orderHistory` / `orderHistoryDrinks`, which deliberately use raw joins/`DB::table` aggregates instead of `whereHas` for the daily totals.
- Livewire flash messages go through `jantinnerezo/livewire-alert` (`$this->alert('success'|'error', ...)`, SweetAlert2 loaded in `layouts/app`).
- Multi-step writes (`stopTimer`, `pindahMeja`) wrap in `DB::beginTransaction()` / `rollBack()`.
- All models soft-delete; Filament resources drop `SoftDeletingScope` in `getEloquentQuery()` and expose Trashed filters + force-delete/restore actions.
