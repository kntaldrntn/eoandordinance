<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('city_employees', function (Blueprint $table) {
            $table->string('pmis_id')->primary(); 
            $table->unsignedBigInteger('id')->nullable(); 
            $table->string('full_name');
            $table->string('position')->nullable();

            $table->foreignId('dept_id')->constrained('departments')->onDelete('cascade');
            $table->integer('state')->default(1)->comment('1=Active, 0=Inactive');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('city_employees');
    }
};