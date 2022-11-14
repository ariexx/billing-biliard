<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::create('orders', function (Blueprint $table) {
			$table->uuid('uuid')->primary();
            $table->uuid('user_uuid');
            $table->uuid('payment_uuid');
			$table->timestamps();
            $table->softDeletes();
		});
	}

	public function down()
	{
		Schema::dropIfExists('orders');
	}
};
