<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('implementing_rules_and_regulations', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('file_path');
            $table->text('disable_reason')->nullable()->after('is_active');
            $table->json('support_offices')->nullable()->after('lead_office_id'); 
        });
    }

    public function down(): void
    {
        Schema::table('implementing_rules_and_regulations', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'disable_reason', 'support_offices']);
        });
    }
};