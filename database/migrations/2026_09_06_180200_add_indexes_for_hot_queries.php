<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk query yang paling sering dijalankan:
 * - active_orders(is_active, product_uuid): dipoll tiap 10 detik oleh setiap
 *   browser kasir yang terbuka, dan dipakai saat mengecek meja terpakai.
 * - orders(user_uuid, created_at): kartu KPI dan riwayat order per kasir.
 * - order_items(created_at): filter utama laporan omzet harian.
 * - products(type): filter join di semua laporan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('active_orders', function (Blueprint $table) {
            $table->index(['is_active', 'product_uuid'], 'active_orders_is_active_product_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_uuid', 'created_at'], 'orders_user_created_index');
            $table->index('order_number', 'orders_order_number_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('created_at', 'order_items_created_at_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('type', 'products_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('active_orders', fn (Blueprint $t) => $t->dropIndex('active_orders_is_active_product_index'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_user_created_index');
            $table->dropIndex('orders_order_number_index');
        });
        Schema::table('order_items', fn (Blueprint $t) => $t->dropIndex('order_items_created_at_index'));
        Schema::table('products', fn (Blueprint $t) => $t->dropIndex('products_type_index'));
    }
};
