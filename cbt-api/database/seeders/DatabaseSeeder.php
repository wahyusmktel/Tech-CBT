<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $credentials = config('cbt.super_admin');

        if ($credentials['email'] && $credentials['username'] && $credentials['password']) {
            User::query()->updateOrCreate(['username' => $credentials['username']], [
                'school_id' => null,
                'name' => 'Super Admin',
                'email' => $credentials['email'],
                'role' => UserRole::SuperAdmin,
                'password' => $credentials['password'],
                'is_active' => true,
            ]);
        }
    }
}
