<?php

namespace Modules\Category\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\Models\User;
use Modules\Category\Models\Category;

/**
 * Category factory for generating test data
 *
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Category>
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'last_updated_by' => User::factory(),
        ];
    }

    /**
     * Create a category for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state([
            'last_updated_by' => $user->id,
        ]);
    }
}
