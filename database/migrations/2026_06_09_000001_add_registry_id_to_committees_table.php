<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->unsignedBigInteger('registry_id')->nullable()->after('type');
            $table->foreign('registry_id')->references('id')->on('committee_registries')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->dropForeign(['registry_id']);
            $table->dropColumn('registry_id');
        });
    }
};
