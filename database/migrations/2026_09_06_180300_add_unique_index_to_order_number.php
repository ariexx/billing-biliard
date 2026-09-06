<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * order_number dibuat 'ORD-'.date('Ymd').'-'.rand(1000,9999): hanya 9000 nilai per
 * hari. Pada 100 order/hari peluang tabrakan sekitar 42% SETIAP HARI, dan tanpa
 * UNIQUE index MySQL menerimanya diam-diam -- dua struk keluar dengan nomor sama
 * dan rekonsiliasi kas jadi ambigu.
 *
 * Generator diganti menjadi urutan harian di App\Models\Order::generateOrderNumber().
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplikat = DB::table('orders')
            ->select('order_number')
            ->groupBy('order_number')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplikat > 0) {
            throw new RuntimeException(
                "Ada {$duplikat} order_number duplikat. Migrasi dibatalkan.\n".
                "Jalankan lebih dulu: php artisan backup:database && php artisan db:integrity-check --fix"
            );
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unique('order_number', 'orders_order_number_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            // Index biasa dari migrasi sebelumnya jadi redundan setelah ada UNIQUE.
            try {
                Schema::table('orders', fn (Blueprint $t) => $t->dropIndex('orders_order_number_index'));
            } catch (\Throwable $e) {
                // Index-nya memang belum ada; abaikan.
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_order_number_unique');
            $table->index('order_number', 'orders_order_number_index');
        });
    }
};
