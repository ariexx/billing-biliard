<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::table('hours', function (Blueprint $table) {
			$table->unsignedBigInteger('price')->default(0)->after('hour');
		});
	}

	public function down()
	{
		Schema::table('hours', function (Blueprint $table) {
			$table->dropColumn('price');
		});
	}
};
