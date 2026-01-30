<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('executive_orders', function (Blueprint $table) {
            // Adding the missing columns
            $table->string('relationship_type')->nullable()->after('amends_eo_id');
        });
    }

    public function down()
    {
        Schema::table('executive_orders', function (Blueprint $table) {
            $table->dropColumn(['relationship_type']);
        });
    }
};