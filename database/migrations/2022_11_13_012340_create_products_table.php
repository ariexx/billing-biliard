<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::create('products', function (Blueprint $table) {
			$table->uuid('uuid')->primary();
            $table->enum('type', ['snack', 'drink', 'billiard', 'other']);
            $table->string('product_code', 50);
            $table->string('name', 255);
            $table->unsignedBigInteger('price');
			$table->timestamps();
            $table->softDeletes();
		});
	}

	public function down()
	{
		Schema::dropIfExists('products');
	}
};
