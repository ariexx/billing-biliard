<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::create('order_items', function (Blueprint $table) {
			$table->uuid('uuid');
            $table->foreignUuid('order_uuid')->references('uuid')->on('orders')->cascadeOnDelete();
            $table->foreignUuid('product_uuid')->references('uuid')->on('products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->integer('price'); //total price of quantity
			$table->timestamps();
            $table->softDeletes();
		});
	}

	public function down()
	{
		Schema::dropIfExists('order_items');
	}
};
