<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::create('hours', function (Blueprint $table) {
			$table->uuid('uuid')->primary();
            $table->integer('hour');
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::dropIfExists('hours');
	}
};
