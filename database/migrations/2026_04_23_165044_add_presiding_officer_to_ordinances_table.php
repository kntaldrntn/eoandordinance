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
    Schema::table('ordinances', function (Blueprint $table) {
        // Adds the new column right after attested_by
        $table->string('presiding_officer')->nullable()->after('attested_by');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropColumn('presiding_officer'); // Removes it if you rollback
        });
    }
};
