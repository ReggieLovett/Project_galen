<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed the two medicine types.
     */
    public function run(): void
    {
        Category::create([
            'name' => 'Over-the-Counter',
            'slug' => Category::OTC,
            'requires_prescription' => false,
        ]);

        Category::create([
            'name' => 'Prescription',
            'slug' => Category::PRESCRIPTION,
            'requires_prescription' => true,
        ]);
    }
}
