<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ordinances', function (Blueprint $table) {
            $table->id();

            // --- 1. PROFILE DETAILS ---
            // "Search EO Number" (In this case, Ordinance Number)
            $table->string('ordinance_number')->unique(); 

            $table->text('title');
            $table->date('date_enacted');
            $table->date('date_approved')->nullable(); 
            $table->date('effectivity_date')->nullable();
            $table->string('attested_by')->nullable(); 
            $table->string('approved_by')->nullable();
            
            $table->foreignId('status_id')->constrained('statuses');
            
            // "Display scanned file"
            $table->string('file_path');

            // --- 2. TRACKING / HISTORY MODULE ---
            // "Parent-child relationship" (Points to another Ordinance ID)
            $table->foreignId('amends_ordinance_id')
                  ->nullable()
                  ->constrained('ordinances')
                  ->nullOnDelete();
                  
            // "Relationship Type" (Amends, Repeals, Supersedes)
            $table->string('relationship_type')->nullable();
            
            // "Details/Tracker" (Your remarks/notes)
            $table->text('remarks')->nullable();

            $table->timestamps();
        });

        // --- 3. OFFICE TAGGING (Pivot Table) ---
        // "Tagging of offices corresponding roles"
        Schema::create('ordinance_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordinance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            
            // Role: 'sponsor', 'implementing', 'support'
            $table->string('role')->default('support'); 
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ordinance_department');
        Schema::dropIfExists('ordinances');
    }
};