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
        Schema::create('committee_member_registry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_registry_id')->constrained('committee_registries')->onDelete('cascade');
            $table->foreignId('committee_member_id')->constrained('committee_members')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('committee_member_registry');
    }
};
