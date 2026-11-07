<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each order line records whether it was a strip or a box, and how many
     * strips it took off the shelf so a cancelled order can put them back.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('packaging')->default('strip')->after('strength');
            $table->unsignedInteger('strip_count')->default(0)->after('quantity');
        });

        DB::table('order_items')->update(['strip_count' => DB::raw('quantity')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['packaging', 'strip_count']);
        });
    }
};
