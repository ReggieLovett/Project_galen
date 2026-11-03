<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel')->default('kiosk');
            $table->string('status');
            $table->unsignedInteger('item_count');
            $table->decimal('total', 10, 2);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Staff dashboard: orders awaiting payment, newest first.
            $table->index(['status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            // Snapshot of the product at the time of sale.
            $table->string('name');
            $table->string('brand');
            $table->string('strength');
            $table->decimal('unit_price', 8, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
