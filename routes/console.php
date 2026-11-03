<?php

use App\Models\Admin;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Admins are never created from the kiosk itself: there is no register page.
Artisan::command('kiosk:admin {email} {--name=Pharmacy Admin}', function (string $email) {
    $password = $this->secret('Password (at least 12 characters)');

    $validator = Validator::make(
        ['email' => $email, 'password' => $password],
        ['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:12']],
    );

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $admin = Admin::updateOrCreate(['email' => $email], ['name' => $this->option('name'), 'password' => $password]);

    $this->info(($admin->wasRecentlyCreated ? 'Created' : 'Updated')." staff admin {$email}.");
})->purpose('Create a staff admin, or reset an existing admin\'s password');
