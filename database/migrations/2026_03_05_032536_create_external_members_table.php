<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('external_members', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('position')->nullable()->comment('e.g., President, Regional Director');
            $table->string('organization')->nullable()->comment('e.g., DepEd, Rotary Club, PNP');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('external_members');
    }
};