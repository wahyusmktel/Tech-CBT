<?php

namespace Database\Seeders;

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
            User::factory()->create([
                'name' => 'Super Admin',
                'email' => $credentials['email'],
                'username' => $credentials['username'],
                'password' => $credentials['password'],
            ]);
        }
    }
}
