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
        // Add to ordinances if missing
        if (!Schema::hasColumn('ordinances', 'external_institutions')) {
            Schema::table('ordinances', function (Blueprint $table) {
                $table->json('external_institutions')->nullable();
            });
        }

        // Add to IRR table
        if (!Schema::hasColumn('implementing_rules_and_regulations', 'external_institutions')) {
            Schema::table('implementing_rules_and_regulations', function (Blueprint $table) {
                $table->json('external_institutions')->nullable();
            });
        }
    }
};
