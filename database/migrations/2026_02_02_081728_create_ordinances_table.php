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
        Schema::create('ordinances', function (Blueprint $table) {
            $table->id();
            
            // --- 1. Ordinance Profiling ---
            $table->string('ordinance_number')->unique(); // "Search Ordinance Number"
            $table->text('title');                        // "Display Title"
            $table->date('date_enacted');                 // "Date Enacted"
            
            // The Signatories
            $table->string('attested_by')->nullable();    // "Attested by" (e.g., SP Secretary)
            $table->string('approved_by')->nullable();    // "Approved by" (e.g., City Mayor)
            
            // Status & File
            $table->foreignId('status_id')->constrained('statuses'); // "Status (Active, Amended...)"
            $table->string('file_path');                  // "Display scanned file"
            
            // --- 2. Update Tracking Module (History) ---
            // This handles the "Parent-child relationship" and "Back tracking"
            $table->foreignId('amends_ordinance_id')->nullable()->constrained('ordinances')->nullOnDelete();
            $table->string('relationship_type')->nullable(); // Amends, Repeals, Supplements
            $table->text('remarks')->nullable();

            $table->timestamps();
        });

        // --- 3. Tagging of Offices (Pivot Table) ---
        // This handles "Tagging of offices corresponding roles"
        Schema::create('ordinance_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordinance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // 'lead' (Implementing) or 'support'
            $table->timestamps();
        });
    }
};
