<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak pembatalan item order.
 *
 * Sebelum ini penghapusan item hanya menyisakan satu baris di log harian yang
 * tidak pernah dibuka siapa pun, sementara barisnya lenyap dari struk dan dari
 * total. Kasir bisa menghapus baris waktu biliar lalu mencetak struk yang hanya
 * berisi minuman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('void_reason')->nullable()->after('hour');
            $table->uuid('voided_by_uuid')->nullable()->after('void_reason');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['void_reason', 'voided_by_uuid']);
        });
    }
};
