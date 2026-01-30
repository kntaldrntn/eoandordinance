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
            $table->text('remarks')->nullable()->after('legal_basis');
        });
    }

    public function down()
    {
        Schema::table('executive_orders', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};
