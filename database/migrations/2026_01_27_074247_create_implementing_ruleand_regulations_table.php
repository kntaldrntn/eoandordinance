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
        Schema::create('implementing_rules_and_regulations', function (Blueprint $table) {
            $table->id();
            // Connect it to the EO
            $table->foreignId('executive_order_id')->constrained()->onDelete('cascade');

            // IRR Details
            $table->string('item_number')->nullable(); // e.g., "Rule I", "Article 2" (Optional)
            $table->string('file_path'); // The PDF
            
            // This status tracks the progress of the IMPLEMENTATION, not the EO validity
            $table->enum('status', ['Drafting', 'Pending Approval', 'Approved', 'Implemented', 'Delayed'])
                ->default('Drafting');

            // Who is responsible for this rule?
            $table->foreignId('lead_office_id')->constrained('departments');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('implementing_rules_and_regulations');
    }
};
