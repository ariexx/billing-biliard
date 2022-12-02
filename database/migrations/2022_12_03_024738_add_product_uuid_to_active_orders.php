<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::table('active_orders', function (Blueprint $table) {
            $table->uuid('product_uuid')->after('order_uuid')->nullable();

            $table->foreign('product_uuid')
                ->references('uuid')
                ->on('products')
                ->onDelete('cascade');
		});
	}

	public function down()
	{
		Schema::table('active_orders', function (Blueprint $table) {
            $table->dropColumn('product_uuid');

            $table->dropForeign('active_orders_product_uuid_foreign');
		});
	}
};
