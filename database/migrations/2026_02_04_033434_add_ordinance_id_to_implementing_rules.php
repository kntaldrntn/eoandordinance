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
        Schema::table('implementing_rules_and_regulations', function (Blueprint $table) {
            // We make it nullable because a rule might belong to an EO, NOT an Ordinance
            $table->foreignId('ordinance_id')
                ->nullable()
                ->after('executive_order_id')
                ->constrained('ordinances')
                ->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::table('implementing_rules', function (Blueprint $table) {
            $table->dropForeign(['ordinance_id']);
            $table->dropColumn('ordinance_id');
        });
    }
};
