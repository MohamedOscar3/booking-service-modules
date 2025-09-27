<?php

namespace Modules\AvailabilityManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Enums\Roles;
use Modules\Auth\Models\User;
use Modules\AvailabilityManagement\Models\AvailabilityManagement;
use Modules\Service\Models\Service;
use NunoMaduro\Collision\Provider;

class AvailabilityManagementDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user =  User::factory()->create(['role' => Roles::PROVIDER]);
        Service::factory(10)->create([
            'provider_id' => $user->id,
        ]);

        AvailabilityManagement::factory()->count(10)->create([
            'provider_id' => $user->id,
        ]);
    }
}
