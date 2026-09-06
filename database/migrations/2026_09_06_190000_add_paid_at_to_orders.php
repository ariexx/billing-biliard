<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sebelum ini tidak ada satu pun penanda lunas di seluruh skema: menutup meja
 * hanya menyetel active_orders.is_active = false, dan mencetak struk cuma
 * menaikkan print_count. Tidak ada cara mengetahui order mana yang sudah dibayar.
 *
 * paid_at NULL = belum lunas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('print_count');
            $table->uuid('paid_by_uuid')->nullable()->after('paid_at');
            $table->index('paid_at', 'orders_paid_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_paid_at_index');
            $table->dropColumn(['paid_at', 'paid_by_uuid']);
        });
    }
};
