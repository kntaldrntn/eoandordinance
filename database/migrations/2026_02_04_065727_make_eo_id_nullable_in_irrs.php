<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('implementing_rules_and_regulations', function (Blueprint $table) {
            $table->unsignedBigInteger('executive_order_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('implementing_rules_and_regulations', function (Blueprint $table) {
            $table->unsignedBigInteger('executive_order_id')->nullable(false)->change();
        });
    }
};