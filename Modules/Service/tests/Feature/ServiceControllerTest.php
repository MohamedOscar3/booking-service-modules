<?php

namespace Modules\Service\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Auth\Enums\Roles;
use Modules\Auth\Models\User;
use Modules\AvailabilityManagement\Services\SlotService;
use Modules\Category\Models\Category;
use Modules\Service\Models\Service;
use Tests\TestCase;

class ServiceControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => Roles::PROVIDER, 'timezone' => 'UTC']);
        $this->admin = User::factory()->create(['role' => Roles::ADMIN, 'timezone' => 'UTC']);
        $this->category = Category::factory()->create(['last_updated_by' => $this->user->id]);
        Sanctum::actingAs($this->user, ['*']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_get_all_services_as_provider(): void
    {
        Service::factory()->count(3)->create(['provider_id' => $this->user->id, 'category_id' => $this->category->id]);
        Service::factory()->count(2)->create(['category_id' => $this->category->id]); // Other provider's services

        $response = $this->getJson('/api/services');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'duration',
                        'price',
                        'provider_id',
                        'category_id',
                        'status',
                        'created_at',
                        'updated_at',
                        'provider',
                        'category',
                    ],
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Services retrieved successfully',
            ])
            ->assertJsonCount(3, 'data'); // Should only see own services as provider
    }

    public function test_can_get_all_services_as_admin(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        Service::factory()->count(5)->create(['category_id' => $this->category->id]);

        $response = $this->getJson('/api/services');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data'); // Admin should see all services
    }

    public function test_can_search_services(): void
    {
        Service::factory()->create(['name' => 'House Cleaning', 'provider_id' => $this->user->id, 'category_id' => $this->category->id]);
        Service::factory()->create(['name' => 'Plumbing', 'provider_id' => $this->user->id, 'category_id' => $this->category->id]);

        $response = $this->getJson('/api/services?q=House');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'House Cleaning');
    }

    public function test_can_filter_services_by_category(): void
    {
        $otherCategory = Category::factory()->create(['last_updated_by' => $this->user->id]);

        Service::factory()->create(['provider_id' => $this->user->id, 'category_id' => $this->category->id]);
        Service::factory()->create(['provider_id' => $this->user->id, 'category_id' => $otherCategory->id]);

        $response = $this->getJson("/api/services?category_id={$this->category->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category_id', $this->category->id);
    }

    public function test_can_filter_services_by_status(): void
    {
        Service::factory()->create(['provider_id' => $this->user->id, 'category_id' => $this->category->id, 'status' => true]);
        Service::factory()->create(['provider_id' => $this->user->id, 'category_id' => $this->category->id, 'status' => false]);

        $response = $this->getJson('/api/services?status=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', true);
    }

    public function test_can_create_service(): void
    {
        $serviceData = [
            'name' => 'House Cleaning Service',
            'description' => 'Professional house cleaning service',
            'duration' => 120,
            'price' => 50.00,
            'category_id' => $this->category->id,
            'status' => true,
        ];

        $response = $this->postJson('/api/services', $serviceData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'duration',
                    'price',
                    'provider_id',
                    'category_id',
                    'status',
                    'created_at',
                    'updated_at',
                    'provider',
                    'category',
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Service created successfully',
                'data' => [
                    'name' => 'House Cleaning Service',
                    'description' => 'Professional house cleaning service',
                    'duration' => 120,
                    'price' => '50.00',
                    'provider_id' => $this->user->id,
                    'category_id' => $this->category->id,
                    'status' => true,
                ],
            ]);

        $this->assertDatabaseHas('services', [
            'name' => 'House Cleaning Service',
            'description' => 'Professional house cleaning service',
            'provider_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);
    }

    public function test_create_service_validation_fails_with_missing_required_fields(): void
    {
        $response = $this->postJson('/api/services', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'description', 'duration', 'price', 'category_id']);
    }

    public function test_create_service_validation_fails_with_invalid_data(): void
    {
        $serviceData = [
            'name' => '', // empty name
            'description' => '', // empty description
            'duration' => 0, // invalid duration
            'price' => -10, // negative price
            'category_id' => 99999, // non-existent category
        ];

        $response = $this->postJson('/api/services', $serviceData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'description', 'duration', 'price', 'category_id']);
    }

    public function test_create_service_validation_fails_with_duplicate_name_for_same_provider(): void
    {
        Service::factory()->create(['name' => 'Existing Service', 'provider_id' => $this->user->id, 'category_id' => $this->category->id]);

        $serviceData = [
            'name' => 'Existing Service',
            'description' => 'Another service with same name',
            'duration' => 60,
            'price' => 25.00,
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/services', $serviceData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_show_specific_service(): void
    {
        $service = Service::factory()->create(['provider_id' => $this->user->id, 'category_id' => $this->category->id]);

        $response = $this->getJson("/api/services/{$service->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'duration',
                    'price',
                    'provider_id',
                    'category_id',
                    'status',
                    'created_at',
                    'updated_at',
                    'provider',
                    'category',
                    'slots',
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Service retrieved successfully',
                'data' => [
                    'id' => $service->id,
                    'name' => $service->name,
                ],
            ]);
    }

    public function test_can_update_service(): void
    {
        $service = Service::factory()->create(['provider_id' => $this->user->id, 'category_id' => $this->category->id]);

        $updateData = [
            'name' => 'Updated Service Name',
            'description' => 'Updated description',
            'duration' => 180,
            'price' => 75.00,
        ];

        $response = $this->putJson("/api/services/{$service->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'duration',
                    'price',
                    'provider_id',
                    'category_id',
                    'status',
                    'created_at',
                    'updated_at',
                    'provider',
                    'category',
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Service updated successfully',
                'data' => [
                    'name' => 'Updated Service Name',
                    'description' => 'Updated description',
                    'duration' => 180,
                    'price' => '75.00',
                ],
            ]);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Updated Service Name',
            'description' => 'Updated description',
            'duration' => 180,
            'price' => 75.00,
        ]);
    }

    public function test_update_service_validation_fails_with_invalid_data(): void
    {
        $service = Service::factory()->create(['provider_id' => $this->user->id, 'category_id' => $this->category->id]);

        $response = $this->putJson("/api/services/{$service->id}", [
            'duration' => 0, // invalid duration
            'price' => -10, // negative price
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['duration', 'price']);
    }

    public function test_can_delete_service(): void
    {
        $service = Service::factory()->create(['provider_id' => $this->user->id, 'category_id' => $this->category->id]);

        $response = $this->deleteJson("/api/services/{$service->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Service deleted successfully',
                'data' => null,
            ]);

        $this->assertSoftDeleted('services', [
            'id' => $service->id,
        ]);
    }

    public function test_cannot_show_nonexistent_service(): void
    {
        $response = $this->getJson('/api/services/99999');

        $response->assertStatus(404);
    }

    public function test_cannot_update_nonexistent_service(): void
    {
        $response = $this->putJson('/api/services/99999', [
            'name' => 'Updated Service',
        ]);

        $response->assertStatus(404);
    }

    public function test_cannot_delete_nonexistent_service(): void
    {
        $response = $this->deleteJson('/api/services/99999');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_services(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/services');

        $response->assertStatus(401);
    }

    public function test_admin_can_filter_by_provider_id(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $otherProvider = User::factory()->create(['role' => Roles::PROVIDER]);

        Service::factory()->create(['provider_id' => $this->user->id, 'category_id' => $this->category->id]);
        Service::factory()->create(['provider_id' => $otherProvider->id, 'category_id' => $this->category->id]);

        $response = $this->getJson("/api/services?provider_id={$this->user->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider_id', $this->user->id);
    }

    public function test_show_service_includes_slots(): void
    {
        $service = Service::factory()->create(['provider_id' => $this->user->id, 'category_id' => $this->category->id]);

        // Mock SlotService
        $slotServiceMock = Mockery::mock(SlotService::class);
        $expectedSlots = [
            '2025-01-15' => ['09:00', '10:00', '11:00'],
            '2025-01-16' => ['14:00', '15:00', '16:00'],
        ];

        $slotServiceMock->shouldReceive('getAvailableSlots')
            ->with($service->id, 'UTC')
            ->once()
            ->andReturn($expectedSlots);

        $this->app->instance(SlotService::class, $slotServiceMock);

        $response = $this->getJson("/api/services/{$service->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.slots', $expectedSlots)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slots',
                ],
            ]);
    }

    public function test_show_service_slots_use_user_timezone(): void
    {
        $this->user->update(['timezone' => 'America/New_York']);
        Sanctum::actingAs($this->user, ['*']);

        $service = Service::factory()->create(['provider_id' => $this->user->id, 'category_id' => $this->category->id]);

        $slotServiceMock = Mockery::mock(SlotService::class);
        $slotServiceMock->shouldReceive('getAvailableSlots')
            ->with($service->id, 'America/New_York')
            ->once()
            ->andReturn([]);

        $this->app->instance(SlotService::class, $slotServiceMock);

        $response = $this->getJson("/api/services/{$service->id}");

        $response->assertStatus(200);
    }

    public function test_show_service_without_availability(): void
    {
        $service = Service::factory()->create(['provider_id' => $this->user->id, 'category_id' => $this->category->id]);

        $slotServiceMock = Mockery::mock(SlotService::class);
        $slotServiceMock->shouldReceive('getAvailableSlots')
            ->with($service->id, 'UTC')
            ->once()
            ->andReturn([]);

        $this->app->instance(SlotService::class, $slotServiceMock);

        $response = $this->getJson("/api/services/{$service->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.slots', [])
            ->assertJson([
                'data' => [
                    'slots' => [],
                ],
            ]);
    }
}
