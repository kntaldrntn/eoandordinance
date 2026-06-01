<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('implementing_rules_and_regulations', function (Blueprint $table) {
            // Add the column to store the external institutions as a JSON array
            $table->json('external_institutions')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('implementing_rules_and_regulations', function (Blueprint $table) {
            // Drop the column if we need to roll back
            $table->dropColumn('external_institutions');
        });
    }
};