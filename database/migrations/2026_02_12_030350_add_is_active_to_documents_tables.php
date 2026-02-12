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
            $table->boolean('is_active')->default(true)->after('status_id'); // Default to Active
        });

        Schema::table('ordinances', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('status_id');
        });
    }

    public function down()
    {
        Schema::table('executive_orders', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
