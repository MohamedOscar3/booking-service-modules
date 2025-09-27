<?php

namespace Modules\Category\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\User;
use Modules\Category\Models\Category;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_can_get_all_categories(): void
    {
        Category::factory()->count(3)->create(['last_updated_by' => $this->user->id]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'last_updated_by',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Categories retrieved successfully',
            ]);
    }

    public function test_can_search_categories(): void
    {
        Category::factory()->create(['name' => 'Technology', 'last_updated_by' => $this->user->id]);
        Category::factory()->create(['name' => 'Health', 'last_updated_by' => $this->user->id]);

        $response = $this->getJson('/api/categories?q=Tech');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Technology');
    }

    public function test_can_create_category(): void
    {
        $categoryData = [
            'name' => 'New Technology Category',
        ];

        $response = $this->postJson('/api/categories', $categoryData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'last_updated_by',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Category created successfully',
                'data' => [
                    'name' => 'New Technology Category',
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'New Technology Category',
            'last_updated_by' => $this->user->id,
        ]);
    }

    public function test_create_category_validation_fails_with_empty_name(): void
    {
        $response = $this->postJson('/api/categories', [
            'name' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_category_validation_fails_with_duplicate_name(): void
    {
        Category::factory()->create(['name' => 'Existing Category', 'last_updated_by' => $this->user->id]);

        $response = $this->postJson('/api/categories', [
            'name' => 'Existing Category',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_show_specific_category(): void
    {
        $category = Category::factory()->create(['last_updated_by' => $this->user->id]);

        $response = $this->getJson("/api/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'last_updated_by',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Category retrieved successfully',
                'data' => [
                    'id' => $category->id,
                    'name' => $category->name,
                ],
            ]);
    }

    public function test_can_update_category(): void
    {
        $category = Category::factory()->create(['last_updated_by' => $this->user->id]);

        $updateData = [
            'name' => 'Updated Category Name',
        ];

        $response = $this->putJson("/api/categories/{$category->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'last_updated_by',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Category updated successfully',
                'data' => [
                    'name' => 'Updated Category Name',
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Category Name',
            'last_updated_by' => $this->user->id,
        ]);
    }

    public function test_update_category_validation_fails_with_empty_name(): void
    {
        $category = Category::factory()->create(['last_updated_by' => $this->user->id]);

        $response = $this->putJson("/api/categories/{$category->id}", [
            'name' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_delete_category(): void
    {
        $category = Category::factory()->create(['last_updated_by' => $this->user->id]);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Category deleted successfully',
                'data' => null,
            ]);

        $this->assertSoftDeleted('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_categories(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/categories');

        $response->assertStatus(401);
    }
}
