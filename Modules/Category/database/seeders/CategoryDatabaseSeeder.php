<?php

namespace Modules\Category\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Models\User;
use Modules\Category\Models\Category;

/**
 * Category database seeder for populating test data
 */
class CategoryDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create categories for existing users or create sample users
        $users = User::limit(3)->get();

        if ($users->isEmpty()) {
            // Create sample users if none exist
            $users = User::factory(3)->create();
        }

        foreach ($users as $user) {

            Category::factory(5)->forUser($user)->create();
        }
    }
}
