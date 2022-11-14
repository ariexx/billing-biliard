<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::create('active_orders', function (Blueprint $table) {
            $table->foreignUuid('order_uuid')->references('uuid')->on('orders')->cascadeOnDelete();
            $table->integer('hour');
            $table->boolean('is_active');
		});
	}

	public function down()
	{
		Schema::dropIfExists('active_orders');
	}
};
