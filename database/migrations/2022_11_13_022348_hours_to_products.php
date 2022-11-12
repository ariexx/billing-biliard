<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::create('hours_to_products', function (Blueprint $table) {
            $table->foreignUuid('hour_uuid')->references('uuid')->on('hours')->cascadeOnDelete();
            $table->foreignUuid('product_uuid')->references('uuid')->on('products')->cascadeOnDelete();
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::dropIfExists('hours_to_products');
	}
};
