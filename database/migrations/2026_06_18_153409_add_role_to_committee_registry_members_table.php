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
        Schema::table('committee_member_registry', function (Blueprint $table) {
            // Adds the role column to your pivot table
            $table->string('role')->default('Member')->after('committee_member_id');
        });
    }

    public function down()
    {
        Schema::table('committee_member_registry', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
