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
        Schema::table('executive_orders', function (Blueprint $table) {
            // Adds the 'declaration' column right after 'legal_basis' to keep the database organized
            $table->text('declaration')->nullable()->after('legal_basis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('executive_orders', function (Blueprint $table) {
            // Drops the column if you ever need to rollback the migration
            $table->dropColumn('declaration');
        });
    }
};