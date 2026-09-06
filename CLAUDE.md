# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Point-of-sale / billing app for a billiard hall ("Black Dragon Pool"). Cashiers open a table, a countdown runs, drinks/snacks get added to the same order, then the receipt is printed. Laravel 9 + Livewire 2 + Filament 2, MySQL, UI copy is mostly Indonesian.

## Commands

```powershell
start.bat                    # what the shop actually runs: schedule:work + serve
php artisan serve            # dev server alone
npm run dev                  # Vite dev server (sass/app.scss + js/app.js)
npm run build                # production assets
php artisan migrate --seed   # schema + Admin/Cashier users (DatabaseSeeder)
vendor/bin/pint              # formatter (Laravel Pint)
php artisan test             # sqlite :memory:, safe to run
php artisan test --filter=BillingTest

php artisan backup:database       # dump .sql.gz -> Google Drive (--no-upload for local only)
php artisan gdrive:authorize      # one-time OAuth -> refresh token for .env
php artisan db:integrity-check    # pre-migration duplicate scan (--fix to repair)
php artisan orders:expire-sessions
```

`.env` needs `PRINTER` (Windows printer name, e.g. `POS-80C`), `RECEIPTPRINTER_PAPER_SIZE`, and the `GOOGLE_DRIVE_*` / `BACKUP_*` keys listed in `.env.example`.

**Windows has no cron**, so `start.bat` launches `php artisan schedule:work` in a second window alongside `php artisan serve`. Close that window and backups and session expiry stop. Backup times are inside opening hours (`BACKUP_SCHEDULE_HOURS`, default 13:00 & 21:00) because the PC is off overnight.

Backups use `ifsnop/mysqldump-php`, not `spatie/laravel-backup`: the `mysqldump` binary is not on PATH on the shop PC.

## Installing on a new shop PC

`start.bat` copies `.env.example` → `.env` and generates `APP_KEY` if missing (the app cannot boot without them, and the wizard itself runs inside the app), then prints the install URL. Everything else is done at **`/install`**: system requirements, DB connection test, `.env`, migrations, admin/cashier accounts, tables + hour packages + payment methods, printer, and Google Drive keys.

Gating is a single file, `storage/installed` (gitignored):
- absent → `RedirectIfNotInstalled` sends **every** route to the wizard. It sits in `$middlewarePriority` **before** `AuthenticatesRequests`; without that, `/home` hits `auth` first and redirects to a login page that cannot work yet.
- present → `AbortIfInstalled` makes the whole `/install` group 404, because it writes `.env` and creates an admin.

To reinstall, delete that file. `writeEnv` backs up the old `.env` alongside it, preserves keys the wizard doesn't know about, and **never overwrites an existing `APP_KEY`** (that would invalidate every session and cookie).

`tests/TestCase::setUp` creates the marker so the redirect doesn't hijack the suite; `InstallTest` removes it and restores it in `tearDown`.

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

- **regular** — prepaid block. `end_at = now() + hour`, the order item is priced at `hour->price` up front. Adding another regular package *extends* the existing session, basing the new end on `max(now(), end_at)` so a lapsed block doesn't get extended into the past.
- **free time** — open-ended play, billed **per minute**: `hours.price` for a free-time package is the rate per minute, so `order_items.price` holds that rate until `ActiveOrder::stopTimer()` closes the session and overwrites it with `minutes * rate`. `stopTimer` filters on `is_active` and locks the row — without that a second click re-multiplies a value that is already the total.
- Settlement is separate from play: `orders.paid_at` (null = unpaid) is set by `POST /order/{uuid}/bayar`, where the cashier picks the payment method. An order cannot be settled while a session is still running.
- Guards in `OrderItemController::update`: a free-time package can't start while a regular block is still running (`end_at > now()` → "Waktu belum habis"), and a regular package can't be added on top of a live free-time session ("Main bebas belum habis").

`resources/views/livewire/meja-grid.blade.php` polls every 10s to render the cards. Expiring finished regular blocks is the job of `orders:expire-sessions` (scheduled every minute) — **not** the view; it used to be an `$order->update()` inside an `@else` branch, which meant sessions only expired while a browser was open. Free-time sessions are deliberately never auto-expired: closing one has to go through `stopTimer()` so it gets billed.

### Schema debts (verified against a production dump)

`order_items` and `active_orders` were created **without a PRIMARY KEY**, and `order_items.uuid` has no index at all, even though both models declare `$primaryKey`. Migrations `2026_09_06_1800*` add the keys, backfill `hour_type`, add hot-path indexes, and make `orders.order_number` unique. They **abort with instructions** rather than run if duplicates exist — always `php artisan backup:database && php artisan db:integrity-check` first. The PK and `MODIFY ENUM` statements are MySQL-only and skip on sqlite so the test suite still runs.

### Cashier dashboard

One Livewire component, `MejaGrid`, renders **every** table — free and occupied — as a single card each. It used to be two components (`Product` + `ActiveOrder`), which made an occupied table appear twice on screen. Merging was also a hard requirement: a single card needs both the "Mulai" and "Selesai" actions, and Livewire actions cannot cross component boundaries.

Card state comes from remaining minutes (`kosong` / `jalan` / `segera` ≤ 10 min / `habis` / `bebas`), exposed as `data-status` and always paired with a text badge — colour is never the only signal. Cards are ordered by name and never re-sorted by state, so a table stays in the same spot.

### Front-end constraints

**`@livewireStyles` must stay in the layout head.** It is the only thing that emits `[wire\:loading]{display:none}`. The layout shipped without it, so every `wire:loading` element rendered permanently visible — a button with `wire:loading.remove` and `wire:loading` spans showed *both* labels at once ("Mulai" and "Memproses…" side by side). Verified in Chrome: with the directive present a `wire:loading` element computes to `display: none` at rest.

**Alpine comes from `@bukScripts(true)`**, not from `app.js` or `package.json` — blade-ui-kit pulls Alpine **v2** off a CDN at the bottom of the layout. That is what makes `<x-countdown>` (`x-data`/`x-text`) tick. Two consequences: the syntax is Alpine 2, not 3; and if that CDN is unreachable the countdown freezes at whatever is inside it, so always put a correct server-rendered value inside `<x-countdown>` rather than an empty placeholder.

Confirmations on `wire:click` buttons use an inline `onclick` that calls `event.stopImmediatePropagation()` when the user cancels. This is verified in Chrome to block Livewire's handler in both of its listener strategies (same-element and delegated), because attribute handlers are registered at parse time, before Livewire initialises. Livewire 2 has no `wire:confirm`.

### Gotchas

- **There is no `orders.total` column.** `Order::getTotalAttribute()` is a pure accessor over `orderItems->sum('price')`. It is deliberately absent from `$fillable`; writing it throws `Unknown column 'total'`. Same for `order_items.total`. The accessor also **shadows any `total` alias** you select on a query that returns `Order` models — `selectRaw('SUM(...) as total')` silently reads back as 0. Alias it something else (`total_omzet`), as `App\Filament\Pages\Laporan` does.
- `order_items.price` is the **total for that line**, not a unit price — for drinks it is `products.price * quantity`, for a time package it is `hours.price`, and for a free-time session it is the per-minute rate until `stopTimer()` overwrites it with the total. Always render money from this column, never from `products.price`, or reprinted receipts stop adding up.
- `Product::getTypeAttribute()` returns `ucfirst($value)`. Queries use lowercase (`where('type', 'billiard')`, `scopeBilliard`), while `$product->type` in PHP/Blade reads `'Billiard'`. Compare against the DB value in queries and the accessor value in PHP.
- `HasUuid` casts to string on purpose. `Str::uuid()` returns an object, so on the request that creates a model `$model->uuid` is an object while the same value read back is a string — `===` between them is false, which silently broke ownership checks.
- `Order::activeOrder()` is a `HasOne` but an order can own several `active_orders` rows (a free-time session appends one). Use `currentSession()` or `activeOrders()` for anything behavioural; the `HasOne` returns an arbitrary row.

## Printing

`POST /print` → `PrintController` bumps `orders.print_count`, writes a line to the `daily` log channel, and returns `order/print-receipt.blade.php`, which renders through `layouts/print` — a CSS-only 80mm receipt printed from the browser. `charlieuki/receiptprinter` and `config/receiptprinter.php` (ESC/POS over the Windows `PRINTER` queue) are installed and configured but **not currently used** by the controller; recent history moved away from direct ESC/POS printing.

## Other conventions

- `rupiah(int $angka)` is a global helper autoloaded via composer `files` (`app/Helpers/rupiah.php`) — use it for all currency output.
- `AppServiceProvider::boot()` pins `date_default_timezone_set('Asia/Jakarta')` and `Carbon::setLocale('id')`.
- Order history tables are **server-side yajra DataTables** (`App\DataTables\OrdersDataTable`), rendered by `HomeController::orderHistory` / `orderHistoryDrinks`, which deliberately use raw joins/`DB::table` aggregates instead of `whereHas` for the daily totals.
- Livewire flash messages go through `jantinnerezo/livewire-alert` (`$this->alert('success'|'error', ...)`, SweetAlert2 loaded in `layouts/app`).
- Multi-step writes (`stopTimer`, `pindahMeja`) wrap in `DB::beginTransaction()` / `rollBack()`.
- All models soft-delete; Filament resources drop `SoftDeletingScope` in `getEloquentQuery()` and expose Trashed filters + force-delete/restore actions.
