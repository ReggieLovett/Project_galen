<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PIECES_PER_STRIP = '(SELECT pieces_per_strip FROM medicines WHERE medicines.id = order_items.medicine_id)';

    /**
     * Customers now buy single pieces (tablets, capsules) or whole boxes,
     * not strips. Stock is counted in pieces and each medicine has a price
     * per piece. Existing stock, prices and order lines are converted.
     */
    public function up(): void
    {
        // Order lines first, while medicines still say how many pieces a strip holds.
        DB::table('order_items')->update([
            'strip_count' => DB::raw('strip_count * '.self::PIECES_PER_STRIP),
        ]);

        DB::table('order_items')->where('packaging', 'strip')->update([
            'quantity' => DB::raw('quantity * '.self::PIECES_PER_STRIP),
            // * 1.0: SQLite would otherwise divide whole-peso prices as integers.
            'unit_price' => DB::raw('ROUND(unit_price * 1.0 / '.self::PIECES_PER_STRIP.', 2)'),
            'packaging' => 'piece',
        ]);

        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('strip_count', 'piece_count');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('packaging')->default('piece')->change();
        });

        DB::table('medicines')->update([
            'stock' => DB::raw('stock * pieces_per_strip'),
            'strip_price' => DB::raw('ROUND(strip_price * 1.0 / pieces_per_strip, 2)'),
        ]);

        Schema::table('medicines', function (Blueprint $table) {
            $table->renameColumn('strip_price', 'piece_price');
        });
    }

    /**
     * Back to strips. Single pieces round up to whole strips.
     */
    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->renameColumn('piece_price', 'strip_price');
        });

        DB::table('medicines')->update([
            'stock' => DB::raw('stock / pieces_per_strip'),
            'strip_price' => DB::raw('strip_price * pieces_per_strip'),
        ]);

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('packaging')->default('strip')->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('piece_count', 'strip_count');
        });

        DB::table('order_items')->where('packaging', 'piece')->update([
            'quantity' => DB::raw('(quantity + '.self::PIECES_PER_STRIP.' - 1) / '.self::PIECES_PER_STRIP),
            'unit_price' => DB::raw('unit_price * '.self::PIECES_PER_STRIP),
            'packaging' => 'strip',
        ]);

        DB::table('order_items')->update([
            'strip_count' => DB::raw('(strip_count + '.self::PIECES_PER_STRIP.' - 1) / '.self::PIECES_PER_STRIP),
        ]);
    }
};
