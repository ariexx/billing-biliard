<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('active_orders', function (Blueprint $table) {
            //get all active orders where hour type is null
            $activeOrders = \App\Models\ActiveOrder::whereNull('hour_type')->get();
            //loop through all active orders, and if the hour is more than 10, set the hour type to 'free time'
            foreach ($activeOrders as $activeOrder) {
                if ($activeOrder->hour > 10) {
                    $activeOrder->hour_type = 'free time';
                    $activeOrder->save();
                }
            }
        });
    }
};
