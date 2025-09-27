<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Models\User;

class AuthDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'password' => 'password',
            'timezone' => 'UTC',
        ]);

        // Create provider user
        User::factory()->create([
            'name' => 'Provider User',
            'email' => 'provider@example.com',
            'role' => 'provider',
            'password' => 'password',
            'timezone' => 'UTC',
        ]);

        // Create regular user
        User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'role' => 'user',
            'password' => 'password',
            'timezone' => 'UTC',
        ]);
    }
}
