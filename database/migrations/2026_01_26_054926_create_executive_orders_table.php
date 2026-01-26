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
        Schema::create('executive_orders', function (Blueprint $table) {
            $table->id();
            $table->string('eo_number')->unique(); // e.g., "EO-2026-001"
            $table->string('title');
            $table->date('date_issued');
            $table->date('effectivity_date')->nullable();
            $table->text('legal_basis')->nullable();
            $table->string('issuing_authority')->default('City Mayor'); 
            $table->string('file_path')->nullable(); // Stores "eos/file.pdf"
            $table->foreignId('status_id')->constrained('statuses'); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('executive_orders');
    }
};
