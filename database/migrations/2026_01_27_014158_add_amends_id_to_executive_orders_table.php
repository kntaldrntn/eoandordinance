<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('executive_orders', function (Blueprint $table) {
            $table->foreignId('amends_eo_id')
                ->nullable()
                ->after('id')
                ->constrained('executive_orders') 
                ->nullOnDelete(); 
        });
    }

    public function down()
    {
        Schema::table('executive_orders', function (Blueprint $table) {
            $table->dropForeign(['amends_eo_id']);
            $table->dropColumn('amends_eo_id');
        });
    }
};
