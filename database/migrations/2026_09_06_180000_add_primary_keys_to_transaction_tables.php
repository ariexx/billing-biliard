<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * order_items dan active_orders dibuat tanpa PRIMARY KEY. Model-nya mendeklarasikan
 * $primaryKey, jadi Eloquent membangun "WHERE uuid = ?" untuk update dan delete --
 * kalau ada uuid duplikat, satu klik hapus menghapus beberapa baris sekaligus, dan
 * setiap pencarian item adalah full table scan.
 *
 * InnoDB juga memakai hidden rowid untuk tabel tanpa PK, yang membuat replikasi dan
 * tooling migrasi online tidak bisa dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // sqlite (test) tidak mendukung ALTER TABLE ADD PRIMARY KEY
        }

        $duplikat = DB::table('order_items')
            ->select('uuid')
            ->groupBy('uuid')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplikat > 0) {
            throw new RuntimeException(
                "Ada {$duplikat} uuid duplikat di order_items. Migrasi dibatalkan supaya data tidak rusak.\n".
                "Jalankan lebih dulu: php artisan backup:database && php artisan db:integrity-check --fix"
            );
        }

        if (! $this->punyaPrimaryKey('order_items')) {
            DB::statement('ALTER TABLE order_items ADD PRIMARY KEY (uuid)');
        }

        if (! $this->punyaPrimaryKey('active_orders')) {
            DB::statement('ALTER TABLE active_orders ADD PRIMARY KEY (unique_id)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if ($this->punyaPrimaryKey('active_orders')) {
            DB::statement('ALTER TABLE active_orders DROP PRIMARY KEY');
        }

        if ($this->punyaPrimaryKey('order_items')) {
            DB::statement('ALTER TABLE order_items DROP PRIMARY KEY');
        }
    }

    private function punyaPrimaryKey(string $table): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', 'PRIMARY')
            ->exists();
    }
};
