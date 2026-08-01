<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Seed the single predefined super admin account.
     */
    public function run(): void
    {
        $email = config('auth.super_admin.email');
        $password = config('auth.super_admin.password');

        if (! filled($email) || ! filled($password)) {
            return;
        }

        $admin = Admin::query()->firstOrNew(['email' => $email]);
        $admin->fill([
            'first_name' => config('auth.super_admin.first_name', 'Super'),
            'last_name' => config('auth.super_admin.last_name', 'Admin'),
            'password' => $password,
            'phone' => config('auth.super_admin.phone'),
        ]);
        $admin->is_super_admin = true;
        $admin->save();
    }
}
