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
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            // The medicine's type: Over-the-Counter or Prescription.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('brand');
            // One canonical name per molecule ("Acetaminophen", never also
            // "Paracetamol") so alternatives can be matched exactly.
            $table->string('active_ingredient')->index();
            $table->string('strength');
            $table->string('dosage_form');
            $table->string('pack_size');
            $table->string('therapeutic_class')->index();
            $table->decimal('price', 8, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();

            $table->unique(['brand', 'active_ingredient', 'strength', 'dosage_form']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
