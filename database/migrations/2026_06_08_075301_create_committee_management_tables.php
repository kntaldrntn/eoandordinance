<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. The Registry of People
        Schema::create('committee_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pmis_id')->nullable()->index(); // Indexed for fast searching
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('agency')->nullable();
            $table->timestamps();
        });

        // 2. The Committee Definition
        Schema::create('committees', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(); // e.g., "City Tourism Council"
            $table->string('type')->default('council'); // 'council', 'program', 'ordinance_sponsors'
            $table->timestamps();
        });

        // 3. Pivot: Linking People to a Committee with a Specific Role
        Schema::create('committee_member_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->constrained('committees')->cascadeOnDelete();
            $table->foreignId('committee_member_id')->constrained('committee_members')->cascadeOnDelete();
            $table->string('role'); // e.g., 'Chairman', 'Vice Chairman', 'Secretariat', 'Member'
            $table->timestamps();
        });

        // 4. Pivot: Linking EOs to Committees
        Schema::create('eo_committee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eo_id')->constrained('executive_orders')->cascadeOnDelete();
            $table->foreignId('committee_id')->constrained('committees')->cascadeOnDelete();
            $table->timestamps();
        });

        // 5. Pivot: Linking Ordinances to Committees
        Schema::create('ordinance_committee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordinance_id')->constrained('ordinances')->cascadeOnDelete();
            $table->foreignId('committee_id')->constrained('committees')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordinance_committee');
        Schema::dropIfExists('eo_committee');
        Schema::dropIfExists('committee_member_assignments');
        Schema::dropIfExists('committees');
        Schema::dropIfExists('committee_members');
    }
};