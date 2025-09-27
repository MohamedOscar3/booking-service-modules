<?php

namespace Modules\Service\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Auth\Models\User;
use Modules\Category\Models\Category;
use Modules\Service\Models\Service;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true).' Service',
            'duration' => $this->faker->numberBetween(30, 240),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'status' => $this->faker->boolean(80), // 80% chance of being active
            'description' => $this->faker->sentences(2, true),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'provider_id' => User::factory(),
            'category_id' => Category::factory(),
        ];
    }
}
