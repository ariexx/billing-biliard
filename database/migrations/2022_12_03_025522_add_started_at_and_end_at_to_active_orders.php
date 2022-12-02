<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::table('active_orders', function (Blueprint $table) {
            $table->dateTime('started_at')->after('is_active');
            $table->dateTime('end_at')->after('started_at');
		});
	}

	public function down()
	{
		Schema::table('active_orders', function (Blueprint $table) {
            $table->dropColumn('started_at');
            $table->dropColumn('end_at');
		});
	}
};
