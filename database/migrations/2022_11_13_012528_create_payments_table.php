<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::create('payments', function (Blueprint $table) {
			$table->uuid('uuid')->primary();
            $table->string('name');
            $table->boolean('is_active');
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::dropIfExists('payments');
	}
};
