<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('executive_orders', function (Blueprint $table) {
            $table->json('committee_details')->nullable()->after('remarks');
        });
    }

    public function down()
    {
        Schema::table('executive_orders', function (Blueprint $table) {
            $table->dropColumn('committee_details');
        });
    }
};