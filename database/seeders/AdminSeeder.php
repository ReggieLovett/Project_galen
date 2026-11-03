<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Seed the demo staff account from KIOSK_ADMIN_EMAIL / KIOSK_ADMIN_PASSWORD.
     * With no password set, a random one is generated and printed once.
     */
    public function run(): void
    {
        $password = config('kiosk.admin.password');

        if (blank($password)) {
            $password = Str::password(16, symbols: false);

            $this->command?->warn("KIOSK_ADMIN_PASSWORD is not set. Generated admin password: {$password}");
        }

        Admin::updateOrCreate(
            ['email' => config('kiosk.admin.email')],
            ['name' => 'Pharmacy Admin', 'password' => $password],
        );
    }
}
