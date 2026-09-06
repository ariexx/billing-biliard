<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill 2024_08_24_103730 hanya mengisi hour_type untuk baris dengan hour > 10,
 * sehingga setiap sesi lama dengan hour <= 10 tetap NULL. Dashboard mencocokkan
 * hour_type ke 'regular' atau 'free time'; baris NULL jatuh ke cabang @else dan
 * langsung ditutup tanpa pernah ditagih.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('active_orders')->whereNull('hour_type')->update(['hour_type' => 'regular']);

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE active_orders MODIFY hour_type ENUM('free time','regular') NOT NULL DEFAULT 'regular'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE active_orders MODIFY hour_type ENUM('free time','regular') NULL");
    }
};
