<?php

namespace Modules\AvailabilityManagement\Tests\Unit;

use App\Services\LoggingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Auth\Enums\Roles;
use Modules\Auth\Models\User;
use Modules\AvailabilityManagement\DTOs\CreateAvailabilityManagementDTO;
use Modules\AvailabilityManagement\DTOs\UpdateAvailabilityManagementDTO;
use Modules\AvailabilityManagement\Enums\SlotType;
use Modules\AvailabilityManagement\Models\AvailabilityManagement;
use Modules\AvailabilityManagement\Services\AvailabilityManagementService;
use Tests\TestCase;

class AvailabilityManagementServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private AvailabilityManagementService $availabilityService;
    private User $provider;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = User::factory()->create(['role' => Roles::PROVIDER]);
        $this->admin = User::factory()->create(['role' => Roles::ADMIN]);

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')->andReturn(true);

        $this->availabilityService = new AvailabilityManagementService($mockLoggingService);
    }

    public function test_get_all_availability_slots_returns_paginated_results(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        AvailabilityManagement::factory()->count(3)->create(['provider_id' => $this->provider->id]);

        $request = new Request();
        $result = $this->availabilityService->getAllAvailabilitySlots($request);

        $this->assertNotNull($result);
        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(3, $result->count());
    }

    public function test_get_all_availability_slots_filters_by_provider_for_provider_role(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        AvailabilityManagement::factory()->count(3)->create(['provider_id' => $this->provider->id]);
        AvailabilityManagement::factory()->count(2)->create(); // Other provider's slots

        $request = new Request();
        $result = $this->availabilityService->getAllAvailabilitySlots($request);

        $this->assertEquals(3, $result->count()); // Should only see own slots
    }

    public function test_get_all_availability_slots_shows_all_for_admin_role(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        AvailabilityManagement::factory()->count(5)->create();

        $request = new Request();
        $result = $this->availabilityService->getAllAvailabilitySlots($request);

        $this->assertEquals(5, $result->count()); // Admin should see all slots
    }

    public function test_get_all_availability_slots_with_type_filter(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::once,
        ]);

        $request = new Request(['type' => 'recurring']);
        $result = $this->availabilityService->getAllAvailabilitySlots($request);

        $this->assertEquals(1, $result->total());
        $this->assertEquals(SlotType::recurring, $result->first()->type);
    }

    public function test_get_all_availability_slots_with_week_day_filter(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => 1,
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => 2,
        ]);

        $request = new Request(['week_day' => '1']);
        $result = $this->availabilityService->getAllAvailabilitySlots($request);

        $this->assertEquals(1, $result->total());
        $this->assertEquals(1, $result->first()->week_day);
    }

    public function test_get_all_availability_slots_with_time_filter(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'from' => '09:00',
            'to' => '17:00',
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'from' => '18:00',
            'to' => '20:00',
        ]);

        $request = new Request(['from' => '09:00']);
        $result = $this->availabilityService->getAllAvailabilitySlots($request);

        $this->assertEquals(1, $result->total());
    }

    public function test_get_all_availability_slots_with_status_filter(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'status' => true,
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'status' => false,
        ]);

        $request = new Request(['status' => '1']);
        $result = $this->availabilityService->getAllAvailabilitySlots($request);

        $this->assertEquals(1, $result->total());
        $this->assertTrue($result->first()->status);
    }

    public function test_get_all_availability_slots_loads_relationships(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        AvailabilityManagement::factory()->create(['provider_id' => $this->provider->id]);

        $request = new Request();
        $result = $this->availabilityService->getAllAvailabilitySlots($request);

        $availability = $result->first();
        $this->assertTrue($availability->relationLoaded('provider'));
    }

    public function test_create_availability_slot_successfully(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $availabilityDto = new CreateAvailabilityManagementDTO(
            provider_id: $this->provider->id,
            type: SlotType::recurring,
            week_day: 1,
            from: '09:00',
            to: '17:00',
            status: true
        );

        $result = $this->availabilityService->createAvailabilitySlot($availabilityDto);

        $this->assertInstanceOf(AvailabilityManagement::class, $result);
        $this->assertEquals($this->provider->id, $result->provider_id);
        $this->assertEquals(SlotType::recurring, $result->type);
        $this->assertEquals(1, $result->week_day);
        $this->assertTrue($result->status);
        $this->assertTrue($result->relationLoaded('provider'));

        $this->assertDatabaseHas('availability_management', [
            'provider_id' => $this->provider->id,
            'type' => 'recurring',
            'week_day' => 1,
            'status' => true,
        ]);
    }

    public function test_create_availability_slot_logs_creation(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Availability slot created', Mockery::type('array'));

        $availabilityService = new AvailabilityManagementService($mockLoggingService);
        $availabilityDto = new CreateAvailabilityManagementDTO(
            provider_id: $this->provider->id,
            type: SlotType::recurring,
            week_day: 1,
            from: '09:00',
            to: '17:00',
            status: true
        );

        $availabilityService->createAvailabilitySlot($availabilityDto);
    }

    public function test_create_availability_slot_throws_exception_and_logs_error(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Failed to create availability slot', Mockery::type('array'));

        // Mock AvailabilityManagement to throw exception
        $this->partialMock(AvailabilityManagement::class, function ($mock) {
            $mock->shouldReceive('create')->andThrow(new \Exception('Database error'));
        });

        $availabilityService = new AvailabilityManagementService($mockLoggingService);
        $availabilityDto = new CreateAvailabilityManagementDTO(
            provider_id: $this->provider->id,
            type: SlotType::recurring,
            week_day: 1,
            from: '09:00',
            to: '17:00',
            status: true
        );

        $this->expectException(\Exception::class);
        $availabilityService->createAvailabilitySlot($availabilityDto);
    }

    public function test_get_availability_slot_by_id_loads_relationships(): void
    {
        $availability = AvailabilityManagement::factory()->create(['provider_id' => $this->provider->id]);

        $result = $this->availabilityService->getAvailabilitySlotById($availability);

        $this->assertEquals($availability->id, $result->id);
        $this->assertTrue($result->relationLoaded('provider'));
    }

    public function test_update_availability_slot_successfully(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $availability = AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => 1,
            'status' => true,
        ]);

        $availabilityDto = new UpdateAvailabilityManagementDTO(
            provider_id: null,
            type: SlotType::once,
            week_day: null,
            from: null,
            to: null,
            status: false
        );

        $result = $this->availabilityService->updateAvailabilitySlot($availability, $availabilityDto);

        $this->assertEquals(SlotType::once, $result->type);
        $this->assertNull($result->week_day);
        $this->assertFalse($result->status);
        $this->assertTrue($result->relationLoaded('provider'));

        $this->assertDatabaseHas('availability_management', [
            'id' => $availability->id,
            'type' => 'once',
            'week_day' => null,
            'status' => false,
        ]);
    }

    public function test_update_availability_slot_with_partial_data(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $availability = AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => 1,
            'status' => true,
        ]);

        $availabilityDto = new UpdateAvailabilityManagementDTO(
            provider_id: null,
            type: null,
            week_day: null,
            from: null,
            to: null,
            status: false
        );

        $result = $this->availabilityService->updateAvailabilitySlot($availability, $availabilityDto);

        $this->assertEquals(SlotType::recurring, $result->type); // Should remain unchanged
        $this->assertEquals(1, $result->week_day); // Should remain unchanged
        $this->assertFalse($result->status); // Should be updated
    }

    public function test_update_availability_slot_logs_update(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $availability = AvailabilityManagement::factory()->create(['provider_id' => $this->provider->id]);

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Availability slot updated', Mockery::type('array'));

        $availabilityService = new AvailabilityManagementService($mockLoggingService);
        $availabilityDto = new UpdateAvailabilityManagementDTO(
            provider_id: null,
            type: null,
            week_day: null,
            from: null,
            to: null,
            status: false
        );

        $availabilityService->updateAvailabilitySlot($availability, $availabilityDto);
    }

    public function test_delete_availability_slot_successfully(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $availability = AvailabilityManagement::factory()->create(['provider_id' => $this->provider->id]);

        $result = $this->availabilityService->deleteAvailabilitySlot($availability);

        $this->assertTrue($result);
        $this->assertSoftDeleted('availability_management', ['id' => $availability->id]);
    }

    public function test_delete_availability_slot_logs_deletion(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $availability = AvailabilityManagement::factory()->create(['provider_id' => $this->provider->id]);

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Availability slot deleted', Mockery::type('array'));

        $availabilityService = new AvailabilityManagementService($mockLoggingService);

        $availabilityService->deleteAvailabilitySlot($availability);
    }

    public function test_get_availability_slots_by_provider_returns_filtered_results(): void
    {
        $otherProvider = User::factory()->create(['role' => Roles::PROVIDER]);

        AvailabilityManagement::factory()->count(3)->create(['provider_id' => $this->provider->id]);
        AvailabilityManagement::factory()->count(2)->create(['provider_id' => $otherProvider->id]);

        $request = new Request();
        $result = $this->availabilityService->getAvailabilitySlotsByProvider($this->provider->id, $request);

        $this->assertEquals(3, $result->total());
        foreach ($result as $availability) {
            $this->assertEquals($this->provider->id, $availability->provider_id);
        }
    }

    public function test_get_availability_slots_by_provider_with_filters(): void
    {
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::once,
        ]);

        $request = new Request(['type' => 'recurring']);
        $result = $this->availabilityService->getAvailabilitySlotsByProvider($this->provider->id, $request);

        $this->assertEquals(1, $result->total());
        $this->assertEquals(SlotType::recurring, $result->first()->type);
    }

    public function test_get_available_slots_returns_active_slots(): void
    {
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'from' => '09:00',
            'to' => '17:00',
            'status' => true,
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'from' => '10:00',
            'to' => '18:00',
            'status' => false, // Inactive
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'from' => '18:00',
            'to' => '20:00',
            'status' => true,
        ]);

        $result = $this->availabilityService->getAvailableSlotsForDate('09:00');

        $this->assertEquals(1, $result->total()); // Only one active slot matching the time
        $this->assertTrue($result->first()->status);
    }

    public function test_get_available_slots_with_provider_filter(): void
    {
        $otherProvider = User::factory()->create(['role' => Roles::PROVIDER]);

        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'from' => '09:00',
            'to' => '17:00',
            'status' => true,
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $otherProvider->id,
            'from' => '10:00',
            'to' => '18:00',
            'status' => true,
        ]);

        $result = $this->availabilityService->getAvailableSlotsForDate('09:00', $this->provider->id);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($this->provider->id, $result->first()->provider_id);
    }

    public function test_get_recurring_availability_returns_recurring_slots_only(): void
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

        $request = new Request();
        $result = $this->availabilityService->getRecurringAvailability($this->provider->id, $request);

        $this->assertEquals(1, $result->total());
        $this->assertEquals(SlotType::recurring, $result->first()->type);
    }

    public function test_get_recurring_availability_with_week_day_filter(): void
    {
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => 1,
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => 2,
        ]);

        $request = new Request(['week_day' => '1']);
        $result = $this->availabilityService->getRecurringAvailability($this->provider->id, $request);

        $this->assertEquals(1, $result->total());
        $this->assertEquals(1, $result->first()->week_day);
    }

    public function test_get_recurring_availability_with_status_filter(): void
    {
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'status' => true,
        ]);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'status' => false,
        ]);

        $request = new Request(['status' => '1']);
        $result = $this->availabilityService->getRecurringAvailability($this->provider->id, $request);

        $this->assertEquals(1, $result->total());
        $this->assertTrue($result->first()->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}