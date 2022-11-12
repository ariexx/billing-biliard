<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::create('billiard_products', function (Blueprint $table) {
			$table->uuid('product_uuid');
            $table->integer('hours');

            $table->foreign('product_uuid')->references('uuid')->on('products')->cascadeOnDelete();
		});
	}

	public function down()
	{
		Schema::dropIfExists('billiard_products');
	}
};
