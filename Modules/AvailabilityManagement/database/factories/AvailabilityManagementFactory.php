<?php

namespace Modules\AvailabilityManagement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Auth\Models\User;
use Modules\AvailabilityManagement\Enums\SlotType;
use Modules\AvailabilityManagement\Models\AvailabilityManagement;

class AvailabilityManagementFactory extends Factory
{
    protected $model = AvailabilityManagement::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement([SlotType::recurring, SlotType::once]);

        $data = [
            'type' => $type,
            'week_day' => $type === SlotType::recurring ? $this->faker->numberBetween(0, 6) : null,

            'status' => $this->faker->boolean(80), // 80% chance of being active
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'provider_id' => User::factory(),
        ];

        if ($data['type'] === SlotType::recurring) {
            $data['from'] = $this->faker->time('H:i');
            $data['to'] = $this->faker->time('H:i');
        } else {
            $data['from'] = $this->faker->dateTimeBetween('-1 week', '+1 week');
            $data['to'] = $this->faker->dateTimeBetween($data['from'], '+1 week');
        }
        return $data;
    }
}
