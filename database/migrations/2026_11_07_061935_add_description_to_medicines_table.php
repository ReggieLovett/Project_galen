<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the medicine is for, in plain words, shown on its card and used
     * by the stock assistant. Label-style uses only, never dosing advice.
     */
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->text('description')->nullable()->after('therapeutic_class');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
