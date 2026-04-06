<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Updates to Executive Orders
        Schema::table('executive_orders', function (Blueprint $table) {
            $table->text('subject_matter')->nullable()->after('title');
            $table->string('classification')->nullable()->after('subject_matter');
        });

        // 2. Updates to Ordinances
        Schema::table('ordinances', function (Blueprint $table) {
            $table->text('subject_matter')->nullable()->after('title');
            // We use JSON here so we can store Primary Author, Co-Authors, and Chairmanships easily
            $table->json('author_details')->nullable()->after('subject_matter'); 
        });

        // 3. Updates to Implementing Rules (IRR)
        Schema::table('implementing_rules_and_regulations', function (Blueprint $table) {
            // We use JSON to store an array of multiple support office IDs
            $table->json('support_office_ids')->nullable()->after('lead_office_id');
        });
    }

    public function down(): void
    {
        Schema::table('executive_orders', function (Blueprint $table) {
            $table->dropColumn(['subject_matter', 'classification']);
        });

        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropColumn(['subject_matter', 'author_details']);
        });

        Schema::table('implementing_rule_and_regulations', function (Blueprint $table) {
            $table->dropColumn(['support_office_ids']);
        });
    }
};