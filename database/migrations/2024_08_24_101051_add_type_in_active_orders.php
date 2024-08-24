<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('active_orders', function (Blueprint $table) {
            $table->enum('hour_type', ['free time', 'regular'])->after('hour')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('active_orders', function (Blueprint $table) {
            $table->dropColumn('hour_type');
        });
    }
};
