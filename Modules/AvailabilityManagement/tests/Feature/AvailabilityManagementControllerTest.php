<?php

namespace Modules\AvailabilityManagement\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Enums\Roles;
use Modules\Auth\Models\User;
use Modules\AvailabilityManagement\Enums\SlotType;
use Modules\AvailabilityManagement\Models\AvailabilityManagement;
use Tests\TestCase;

class AvailabilityManagementControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $provider;
    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = User::factory()->create(['role' => Roles::PROVIDER]);
        $this->admin = User::factory()->create(['role' => Roles::ADMIN]);
        $this->user = User::factory()->create(['role' => Roles::USER]);

        Sanctum::actingAs($this->provider, ['*']);
    }

    public function test_can_get_all_availability_slots_as_provider(): void
    {
        AvailabilityManagement::factory()->count(3)->create(['provider_id' => $this->provider->id]);
        AvailabilityManagement::factory()->count(2)->create(); // Other provider's slots

        $response = $this->getJson('/api/availability-management');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'provider_id',
                        'type',
                        'week_day',
                        'from',
                        'to',
                        'status',
                        'created_at',
                        'updated_at',
                        'provider',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJson([
                'status' => true,
                'message' => 'Availability slots retrieved successfully',
            ])
            ->assertJsonCount(3, 'data'); // Should only see own slots as provider
    }

    public function test_can_get_all_availability_slots_as_admin(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        AvailabilityManagement::factory()->count(5)->create();

        $response = $this->getJson('/api/availability-management');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data'); // Admin should see all slots
    }

    public function test_can_filter_availability_slots_by_type(): void
    {
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::once,
        ]);

        $response = $this->getJson('/api/availability-management?type=recurring');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'recurring');
    }

    public function test_can_filter_availability_slots_by_week_day(): void
    {
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => 1,
            'from' => '09:00',
            'to' => '17:00',
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => 2,
            'from' => '09:00',
            'to' => '17:00',
        ]);

        $response = $this->getJson('/api/availability-management?week_day=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.week_day', 1);
    }

    public function test_can_filter_availability_slots_by_status(): void
    {
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'status' => true,
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'status' => false,
        ]);

        $response = $this->getJson('/api/availability-management?status=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', true);
    }

    public function test_can_create_recurring_availability_slot(): void
    {
        $availabilityData = [
            'type' => 'recurring',
            'week_day' => 1,
            'from' => '09:00',
            'to' => '17:00',
            'status' => true,
        ];

        $response = $this->postJson('/api/availability-management', $availabilityData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'provider_id',
                    'type',
                    'week_day',
                    'from',
                    'to',
                    'status',
                    'created_at',
                    'updated_at',
                    'provider',
                ],
            ])
            ->assertJson([
                'status' => true,
                'message' => 'Availability slot created successfully',
                'data' => [
                    'type' => 'recurring',
                    'week_day' => 1,
                    'provider_id' => $this->provider->id,
                    'status' => true,
                ],
            ]);

        $this->assertDatabaseHas('availability_management', [
            'type' => 'recurring',
            'week_day' => 1,
            'provider_id' => $this->provider->id,
        ]);
    }

    public function test_can_create_once_availability_slot(): void
    {
        $availabilityData = AvailabilityManagement::factory()->make([
            'type' => 'once',
            'from'=> Carbon::now()->addDay(),
            'to'=> Carbon::now()->addDays(2),
        ])->toArray();

        $response = $this->postJson('/api/availability-management', $availabilityData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'provider_id',
                    'type',
                    'week_day',
                    'from',
                    'to',
                    'status',
                    'created_at',
                    'updated_at',
                    'provider',
                ],
            ]);


    }

    public function test_create_availability_slot_validation_fails_with_missing_required_fields(): void
    {
        $response = $this->postJson('/api/availability-management', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'from', 'to']);
    }

    public function test_create_recurring_slot_validation_fails_without_week_day(): void
    {
        $availabilityData = [
            'type' => 'recurring',
            'from' => '09:00',
            'to' => '17:00',
        ];

        $response = $this->postJson('/api/availability-management', $availabilityData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['week_day']);
    }

    public function test_create_availability_slot_validation_fails_with_invalid_data(): void
    {
        $availabilityData = [
            'type' => 'invalid_type',
            'week_day' => 10, // invalid week day
            'from' => 'invalid_time',
            'to' => 'invalid_time',
        ];

        $response = $this->postJson('/api/availability-management', $availabilityData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'week_day']);
    }

    public function test_can_show_specific_availability_slot(): void
    {
        $availability = AvailabilityManagement::factory()->create(['provider_id' => $this->provider->id]);

        $response = $this->getJson("/api/availability-management/{$availability->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'provider_id',
                    'type',
                    'week_day',
                    'from',
                    'to',
                    'status',
                    'created_at',
                    'updated_at',
                    'provider',
                ],
            ])
            ->assertJson([
                'status' => true,
                'message' => 'Availability slot retrieved successfully',
                'data' => [
                    'id' => $availability->id,
                    'provider_id' => $availability->provider_id,
                ],
            ]);
    }

    public function test_can_update_availability_slot(): void
    {
        $availability = AvailabilityManagement::factory()->create(['provider_id' => $this->provider->id]);

        $updateData = [
            'type' => 'once',
            'week_day' => null,
            'status' => false,
        ];

        $response = $this->putJson("/api/availability-management/{$availability->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Availability slot updated successfully',
                'data' => [
                    'id' => $availability->id,
                    'type' => 'once',
                    'week_day' => null,
                    'status' => false,
                    'provider_id' => $this->provider->id,
                    'provider' => [
                        'id' => $this->provider->id,
                        'name' => $this->provider->name,
                    ],
                ],
            ]);
    }

    public function test_can_delete_availability_slot(): void
    {
        $availability = AvailabilityManagement::factory()->create(['provider_id' => $this->provider->id]);

        $response = $this->deleteJson("/api/availability-management/{$availability->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Availability slot deleted successfully',
                'data' => null,
            ]);

        $this->assertSoftDeleted('availability_management', [
            'id' => $availability->id,
        ]);
    }

    public function test_provider_cannot_update_other_provider_slot(): void
    {
        $otherProvider = User::factory()->create(['role' => Roles::PROVIDER]);
        $availability = AvailabilityManagement::factory()->create(['provider_id' => $otherProvider->id]);

        $response = $this->putJson("/api/availability-management/{$availability->id}", [
            'status' => false,
        ]);

        $response->assertStatus(403); // Forbidden
    }

    public function test_provider_cannot_delete_other_provider_slot(): void
    {
        $otherProvider = User::factory()->create(['role' => Roles::PROVIDER]);
        $availability = AvailabilityManagement::factory()->create(['provider_id' => $otherProvider->id]);

        $response = $this->deleteJson("/api/availability-management/{$availability->id}");

        $response->assertStatus(403); // Forbidden
    }

    public function test_admin_can_update_any_provider_slot(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $availability = AvailabilityManagement::factory()->create(['provider_id' => $this->provider->id]);

        $response = $this->putJson("/api/availability-management/{$availability->id}", [
            'status' => false,
        ]);

        $response->assertStatus(200);
    }

    public function test_admin_can_delete_any_provider_slot(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $availability = AvailabilityManagement::factory()->create(['provider_id' => $this->provider->id]);

        $response = $this->deleteJson("/api/availability-management/{$availability->id}");

        $response->assertStatus(200);
    }

    public function test_user_cannot_create_availability_slot(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $availabilityData = [
            'type' => 'recurring',
            'week_day' => 1,
            'from' => '09:00',
            'to' => '17:00',
        ];

        $response = $this->postJson('/api/availability-management', $availabilityData);

        $response->assertStatus(403); // Forbidden
    }

    public function test_cannot_show_nonexistent_availability_slot(): void
    {
        $response = $this->getJson('/api/availability-management/99999');

        $response->assertStatus(404);
    }

    public function test_cannot_update_nonexistent_availability_slot(): void
    {
        $response = $this->putJson('/api/availability-management/99999', [
            'status' => false,
        ]);

        $response->assertStatus(404);
    }

    public function test_cannot_delete_nonexistent_availability_slot(): void
    {
        $response = $this->deleteJson('/api/availability-management/99999');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_availability_slots(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/availability-management');

        $response->assertStatus(401);
    }

    public function test_can_get_availability_slots_by_provider(): void
    {
        $otherProvider = User::factory()->create(['role' => Roles::PROVIDER]);

        AvailabilityManagement::factory()->count(2)->create(['provider_id' => $this->provider->id]);
        AvailabilityManagement::factory()->count(3)->create(['provider_id' => $otherProvider->id]);

        $response = $this->getJson("/api/availability-management/provider/{$this->provider->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.provider_id', $this->provider->id);
    }

    public function test_can_get_available_slots_by_time(): void
    {
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'from' => '09:00',
            'to' => '17:00',
            'status' => true,
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'from' => '18:00',
            'to' => '20:00',
            'status' => true,
        ]);

        $response = $this->getJson('/api/availability-management?from=09:00');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_get_recurring_availability_for_provider(): void
    {
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => 1,
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::once,
        ]);

        $response = $this->getJson("/api/availability-management/provider/{$this->provider->id}/recurring");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'recurring');
    }
}
