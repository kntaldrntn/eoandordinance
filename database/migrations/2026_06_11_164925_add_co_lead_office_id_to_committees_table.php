<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only add the column if it doesn't already exist
        if (!Schema::hasColumn('committees', 'co_lead_office_id')) {
            Schema::table('committees', function (Blueprint $table) {
                $table->unsignedBigInteger('co_lead_office_id')->nullable()->after('type');
                $table->foreign('co_lead_office_id')
                      ->references('id')
                      ->on('departments')
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('committees', 'co_lead_office_id')) {
            Schema::table('committees', function (Blueprint $table) {
                $table->dropForeign(['co_lead_office_id']);
                $table->dropColumn('co_lead_office_id');
            });
        }
    }
};