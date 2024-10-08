<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::table('active_orders', function (Blueprint $table) {
            $table->string('unique_id')->unique()->after('order_uuid');
		});
	}

	public function down()
	{
		Schema::table('active_orders', function (Blueprint $table) {
            $table->dropColumn('unique_id');
		});
	}
};
