<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::table('order_items', function (Blueprint $table) {
            $table->string('active_order_unique_id')->after('product_uuid')->nullable();

            $table->foreign('active_order_unique_id')
                ->references('unique_id')
                ->on('active_orders')
                ->onDelete('cascade');
		});
	}

	public function down()
	{
		Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['active_order_unique_id']);
            $table->dropColumn('active_order_unique_id');
		});
	}
};
