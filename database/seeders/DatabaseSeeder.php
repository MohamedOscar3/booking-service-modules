<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Database\Seeders\AuthDatabaseSeeder;
use Modules\AvailabilityManagement\Database\Seeders\AvailabilityManagementDatabaseSeeder;
use Modules\Category\Database\Seeders\CategoryDatabaseSeeder;
use Modules\Service\Database\Seeders\ServiceDatabaseSeeder;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AuthDatabaseSeeder::class,
            CategoryDatabaseSeeder::class,
            ServiceDatabaseSeeder::class,
            AvailabilityManagementDatabaseSeeder::class,
        ]);
    }
}
