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
        Schema::table('ordinances', function (Blueprint $table) {
        // LONGTEXT is perfect because PDFs can contain thousands of words
        $table->longText('document_content')->nullable()->after('subject_matter');
        });

        Schema::table('executive_orders', function (Blueprint $table) {
            $table->longText('document_content')->nullable()->after('subject_matter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropColumn('document_content');

        });

        Schema::table('executive_orders', function (Blueprint $table) {
            $table->dropColumn('document_content');
        });
    }
};
