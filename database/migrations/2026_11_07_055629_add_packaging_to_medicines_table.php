<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Medicines are sold by the strip or by the box. Stock is counted in
     * strips, and a box is `strips_per_box` strips at its own price.
     */
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->renameColumn('price', 'strip_price');
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->unsignedTinyInteger('pieces_per_strip')->default(10)->after('dosage_form');
            $table->unsignedTinyInteger('strips_per_box')->default(10)->after('pieces_per_strip');
            $table->decimal('box_price', 10, 2)->default(0)->after('strip_price');
            // Optional product photo, relative to public/. Without one the kiosk draws the pack.
            $table->string('image_path')->nullable()->after('box_price');
            $table->dropColumn('pack_size');
        });

        DB::table('medicines')->update(['box_price' => DB::raw('strip_price * strips_per_box')]);
    }

    /**
     * Reverse the migrations. Box prices and photos are lost.
     */
    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('pack_size')->default('');
            $table->dropColumn(['pieces_per_strip', 'strips_per_box', 'box_price', 'image_path']);
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->renameColumn('strip_price', 'price');
        });
    }
};
