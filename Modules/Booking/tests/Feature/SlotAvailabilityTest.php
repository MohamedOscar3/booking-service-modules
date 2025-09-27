<?php

namespace Modules\Booking\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\Roles;
use Modules\Auth\Models\User;
use Modules\AvailabilityManagement\Enums\SlotType;
use Modules\AvailabilityManagement\Models\AvailabilityManagement;
use Modules\Booking\Enums\BookingStatusEnum;
use Modules\Booking\Models\Booking;
use Modules\Service\Models\Service;
use Tests\TestCase;

class SlotAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $provider;

    protected User $client;

    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = User::factory()->create(['role' => Roles::PROVIDER]);
        $this->client = User::factory()->create(['role' => Roles::USER]);

        $this->service = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'duration' => 60, // 1 hour
            'price' => 100.00,
        ]);
    }

    public function test_no_availability_when_no_slots_defined(): void
    {
        $this->actingAs($this->client);

        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'available_slots' => [],
                ],
            ]);
    }

    public function test_weekly_recurring_slots_availability(): void
    {
        // Create recurring slot for Mondays 9-17
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => 1, // Monday
            'from' => Carbon::parse('09:00:00'),
            'to' => Carbon::parse('17:00:00'),
            'status' => 1,
        ]);

        $this->actingAs($this->client);

        // Test on a Monday
        $nextMonday = Carbon::now()->next(Carbon::MONDAY);

        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => $nextMonday->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200);
        $availableSlots = $response->json('data.available_slots');

        // Should have slots from 09:00 to 16:00 (last slot that fits 1-hour service)
        $this->assertContains('09:00', $availableSlots);
        $this->assertContains('16:00', $availableSlots);
        $this->assertNotContains('17:00', $availableSlots); // Can't fit 1-hour service

        // Test on Tuesday (should have no slots)
        $nextTuesday = $nextMonday->copy()->addDay();

        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => $nextTuesday->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'available_slots' => [],
                ],
            ]);
    }

    public function test_one_time_slots_availability(): void
    {
        $specificDate = Carbon::tomorrow();

        // Create one-time slot for tomorrow
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::once,
            'week_day' => null,
            'from' => $specificDate->copy()->setTime(14, 0),
            'to' => $specificDate->copy()->setTime(16, 0),
            'status' => 1, // Available
        ]);

        $this->actingAs($this->client);

        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => $specificDate->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200);
        $availableSlots = $response->json('data.available_slots');

        $this->assertContains('14:00', $availableSlots);
        $this->assertContains('15:00', $availableSlots);
        $this->assertNotContains('16:00', $availableSlots); // Can't fit 1-hour service
    }

    public function test_blocked_one_time_slots(): void
    {
        $specificDate = Carbon::tomorrow();

        // Create regular recurring availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => $specificDate->dayOfWeek,
            'from' => $specificDate->copy()->setTime(9, 0),
            'to' => $specificDate->copy()->setTime(17, 0),
            'status' => 1,
        ]);

        // Create blocked slot for specific time
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::once,
            'week_day' => null,
            'from' => $specificDate->copy()->setTime(14, 0),
            'to' => $specificDate->copy()->setTime(15, 0),
            'status' => 0, // Blocked
        ]);

        $this->actingAs($this->client);

        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => $specificDate->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200);
        $availableSlots = $response->json('data.available_slots');

        // 14:00 should be blocked
        $this->assertNotContains('14:00', $availableSlots);
        // But 13:00 and 15:00 should be available
        $this->assertContains('13:00', $availableSlots);
        $this->assertContains('15:00', $availableSlots);
    }

    public function test_slots_blocked_by_existing_bookings(): void
    {
        $testDate = Carbon::tomorrow();

        // Create availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => $testDate->dayOfWeek,
            'from' => $testDate->copy()->setTime(9, 0),
            'to' => $testDate->copy()->setTime(17, 0),
            'status' => 1,
        ]);

        // Create existing booking at 14:00
        Booking::factory()->create([
            'user_id' => User::factory()->create()->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'date' => $testDate->copy()->setTime(14, 0),
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->client);

        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => $testDate->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200);
        $availableSlots = $response->json('data.available_slots');

        // 14:00 should not be available due to existing booking
        $this->assertNotContains('14:00', $availableSlots);
        // Other slots should be available
        $this->assertContains('13:00', $availableSlots);
        $this->assertContains('15:00', $availableSlots);
    }

    public function test_cancelled_bookings_dont_block_slots(): void
    {
        $testDate = Carbon::tomorrow();

        // Create availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => $testDate->dayOfWeek,
            'from' => $testDate->copy()->setTime(9, 0),
            'to' => $testDate->copy()->setTime(17, 0),
            'status' => 1,
        ]);

        // Create cancelled booking at 14:00
        Booking::factory()->create([
            'user_id' => User::factory()->create()->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'date' => $testDate->copy()->setTime(14, 0),
            'status' => BookingStatusEnum::CANCELLED,
        ]);

        $this->actingAs($this->client);

        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => $testDate->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200);
        $availableSlots = $response->json('data.available_slots');

        // 14:00 should be available despite cancelled booking
        $this->assertContains('14:00', $availableSlots);
    }

    public function test_slots_blocked_by_longer_duration_services(): void
    {
        $testDate = Carbon::tomorrow();

        // Create a 2-hour service
        $longService = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'duration' => 120, // 2 hours
            'price' => 200.00,
        ]);

        // Create availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => $testDate->dayOfWeek,
            'from' => $testDate->copy()->setTime(9, 0),
            'to' => $testDate->copy()->setTime(17, 0),
            'status' => 1,
        ]);

        // Book 2-hour service at 14:00 (blocks until 16:00)
        Booking::factory()->create([
            'user_id' => User::factory()->create()->id,
            'service_id' => $longService->id,
            'provider_id' => $this->provider->id,
            'date' => $testDate->copy()->setTime(14, 0),
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->client);

        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id, // 1-hour service
            'date' => $testDate->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200);
        $availableSlots = $response->json('data.available_slots');

        // 14:00 and 15:00 should be blocked by 2-hour booking
        $this->assertNotContains('14:00', $availableSlots);
        $this->assertNotContains('15:00', $availableSlots);
        // 13:00 and 16:00 should be available
        $this->assertContains('13:00', $availableSlots);
        $this->assertContains('16:00', $availableSlots);
    }

    public function test_user_own_bookings_block_slots(): void
    {
        $testDate = Carbon::tomorrow();

        // Create availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => $testDate->dayOfWeek,
            'from' => $testDate->copy()->setTime(9, 0),
            'to' => $testDate->copy()->setTime(17, 0),
            'status' => 1,
        ]);

        // Create booking for the client at 14:00
        Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'date' => $testDate->copy()->setTime(14, 0),
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->client);

        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => $testDate->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200);
        $availableSlots = $response->json('data.available_slots');

        // Client's own booking should block the slot
        $this->assertNotContains('14:00', $availableSlots);
    }

    public function test_check_slot_availability_endpoint(): void
    {
        $testDate = Carbon::tomorrow();

        // Create availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => $testDate->dayOfWeek,
            'from' => $testDate->copy()->setTime(9, 0),
            'to' => $testDate->copy()->setTime(17, 0),
            'status' => 1,
        ]);

        $this->actingAs($this->client);

        // Check available slot
        $response = $this->postJson('/api/v1/bookings/check-slot', [
            'service_id' => $this->service->id,
            'datetime' => $testDate->copy()->setTime(14, 0)->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'available' => true,
                ],
            ]);

        // Create booking to block the slot
        Booking::factory()->create([
            'user_id' => User::factory()->create()->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'date' => $testDate->copy()->setTime(14, 0),
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        // Check now unavailable slot
        $response = $this->postJson('/api/v1/bookings/check-slot', [
            'service_id' => $this->service->id,
            'datetime' => $testDate->copy()->setTime(14, 0)->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'available' => false,
                ],
            ]);
    }

    public function test_past_slots_not_available(): void
    {
        $today = Carbon::today();

        // Create availability for today
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => $today->dayOfWeek,
            'from' => $today->copy()->setTime(9, 0),
            'to' => $today->copy()->setTime(17, 0),
            'status' => 1,
        ]);

        $this->actingAs($this->client);

        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => $today->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200);
        $availableSlots = $response->json('data.available_slots');

        $currentHour = Carbon::now()->format('H:i');

        // Past slots should not be available
        foreach ($availableSlots as $slot) {
            $this->assertGreaterThanOrEqual($currentHour, $slot);
        }
    }

    public function test_timezone_handling_in_availability(): void
    {
        $testDate = Carbon::tomorrow();

        // Create availability in UTC
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => $testDate->dayOfWeek,
            'from' => $testDate->copy()->setTime(9, 0), // 9 AM UTC
            'to' => $testDate->copy()->setTime(17, 0), // 5 PM UTC
            'status' => 1,
        ]);

        $this->actingAs($this->client);

        // Request in Eastern Time (UTC-5)
        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => $testDate->format('Y-m-d'),
            'timezone' => 'America/New_York',
        ]));

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'timezone' => 'America/New_York',
                ],
            ]);

        // Should have appropriate slots for the timezone
        $this->assertNotEmpty($response->json('data.available_slots'));
    }

    public function test_service_duration_affects_last_available_slot(): void
    {
        $testDate = Carbon::tomorrow();

        // Create 3-hour service
        $longService = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'duration' => 180, // 3 hours
            'price' => 300.00,
        ]);

        // Create availability 9-17 (8 hours)
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => $testDate->dayOfWeek,
            'from' => $testDate->copy()->setTime(9, 0),
            'to' => $testDate->copy()->setTime(17, 0),
            'status' => 1,
        ]);

        $this->actingAs($this->client);

        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $longService->id,
            'date' => $testDate->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200);
        $availableSlots = $response->json('data.available_slots');

        // Last slot should be 14:00 (14:00-17:00 = 3 hours)
        $this->assertContains('14:00', $availableSlots);
        $this->assertNotContains('15:00', $availableSlots); // Can't fit 3-hour service
        $this->assertNotContains('16:00', $availableSlots);
        $this->assertNotContains('17:00', $availableSlots);
    }
}
