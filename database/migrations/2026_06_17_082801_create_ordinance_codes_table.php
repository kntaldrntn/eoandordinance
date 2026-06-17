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
        Schema::create('ordinance_codes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., "The Revenue Code"
            $table->text('description')->nullable(); 
            $table->timestamps();
        });

        // Now, add the foreign key to your existing ordinances table
        Schema::table('ordinances', function (Blueprint $table) {
            $table->foreignId('ordinance_code_id')->nullable()->constrained('ordinance_codes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropForeign(['ordinance_code_id']);
            $table->dropColumn('ordinance_code_id');
        });
    }
};
