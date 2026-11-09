<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the kiosk demo: medicine types, the catalogue, one loyalty
     * member with prescription history, and a staff admin.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            MedicineSeeder::class,
            DemoCustomerSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
