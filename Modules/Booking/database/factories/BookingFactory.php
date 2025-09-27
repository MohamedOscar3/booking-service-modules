<?php

namespace Modules\Booking\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\Models\User;
use Modules\Booking\Enums\BookingStatusEnum;
use Modules\Booking\Models\Booking;
use Modules\Service\Models\Service;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('now', '+30 days');
        $time = $this->faker->time('H:i:s');

        return [
            'date' => $date,
            'time' => $time,
            'status' => $this->faker->randomElement(BookingStatusEnum::cases()),
            'price' => $this->faker->randomFloat(2, 50, 500),
            'service_description' => $this->faker->sentence(),
            'service_name' => $this->faker->words(3, true),
            'provider_notes' => $this->faker->optional()->sentence(),
            'customer_notes' => $this->faker->optional()->sentence(),
            'user_id' => User::factory(),
            'service_id' => Service::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatusEnum::PENDING,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatusEnum::CONFIRMED,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatusEnum::COMPLETED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatusEnum::CANCELLED,
        ]);
    }
}
