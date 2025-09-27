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

class BookingConstraintsTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected User $provider;

    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['role' => Roles::USER]);
        $this->provider = User::factory()->create(['role' => Roles::PROVIDER]);

        $this->service = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'duration' => 60,
            'price' => 100.00,
        ]);
    }

    public function test_cannot_book_more_than_6_months_in_advance(): void
    {
        $this->actingAs($this->client);

        $farFutureDate = Carbon::now()->addMonths(7);

        $bookingData = [
            'service_id' => $this->service->id,
            'date' => $farFutureDate->format('Y-m-d'),
            'time' => '14:00',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_can_book_exactly_6_months_in_advance(): void
    {
        // Create availability
        $sixMonthsDate = Carbon::now()->addMonths(6);
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => $sixMonthsDate->dayOfWeek,
            'from' => $sixMonthsDate->copy()->setTime(9, 0),
            'to' => $sixMonthsDate->copy()->setTime(17, 0),
            'status' => 1,
        ]);

        $this->actingAs($this->client);

        $bookingData = [
            'service_id' => $this->service->id,
            'date' => $sixMonthsDate->format('Y-m-d'),
            'time' => '14:00',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(201);
    }

    public function test_validation_requires_all_fields(): void
    {
        $this->actingAs($this->client);

        // Missing required fields
        $response = $this->postJson('/api/v1/bookings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'service_id',
                'date',
                'time',
                'timezone',
            ]);
    }

    public function test_service_id_must_exist(): void
    {
        $this->actingAs($this->client);

        $bookingData = [
            'service_id' => 99999, // Non-existent service
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'time' => '14:00',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_id']);
    }

    public function test_invalid_time_format_rejected(): void
    {
        $this->actingAs($this->client);

        $bookingData = [
            'service_id' => $this->service->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'time' => '25:99', // Invalid time
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['time']);
    }

    public function test_invalid_timezone_rejected(): void
    {
        $this->actingAs($this->client);

        $bookingData = [
            'service_id' => $this->service->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'time' => '14:00',
            'timezone' => 'Invalid/Timezone',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['timezone']);
    }

    public function test_concurrent_booking_attempts_handling(): void
    {
        // Create availability
        $testDate = Carbon::tomorrow();
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => $testDate->dayOfWeek,
            'from' => $testDate->copy()->setTime(9, 0),
            'to' => $testDate->copy()->setTime(17, 0),
            'status' => 1,
        ]);

        // First booking succeeds
        $this->actingAs($this->client);

        $bookingData = [
            'service_id' => $this->service->id,
            'date' => $testDate->format('Y-m-d'),
            'time' => '14:00',
            'timezone' => 'UTC',
        ];

        $response1 = $this->postJson('/api/v1/bookings', $bookingData);
        $response1->assertStatus(201);

        // Second identical booking should fail
        $anotherClient = User::factory()->create(['role' => Roles::USER]);
        $this->actingAs($anotherClient);

        $response2 = $this->postJson('/api/v1/bookings', $bookingData);
        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['time']);
    }

    public function test_overlapping_booking_detection(): void
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

        // Create 2-hour service
        $longService = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'duration' => 120,
            'price' => 200.00,
        ]);

        // Book 2-hour service at 14:00 (blocks 14:00-16:00)
        Booking::factory()->create([
            'user_id' => User::factory()->create()->id,
            'service_id' => $longService->id,
            'provider_id' => $this->provider->id,
            'date' => $testDate->copy()->setTime(14, 0),
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->client);

        // Try to book 1-hour service at 15:00 (overlaps)
        $bookingData = [
            'service_id' => $this->service->id,
            'date' => $testDate->format('Y-m-d'),
            'time' => '15:00',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['time']);
    }

    public function test_customer_notes_length_validation(): void
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

        $bookingData = [
            'service_id' => $this->service->id,
            'date' => $testDate->format('Y-m-d'),
            'time' => '14:00',
            'timezone' => 'UTC',
            'customer_notes' => str_repeat('a', 1001), // Too long
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_notes']);
    }

    public function test_unauthorized_user_cannot_book(): void
    {
        // Not authenticated
        $bookingData = [
            'service_id' => $this->service->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'time' => '14:00',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(401);
    }

    public function test_status_transition_constraints(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::COMPLETED,
        ]);

        $this->actingAs($this->provider);

        // Cannot cancel completed booking
        $response = $this->postJson("/api/v1/bookings/{$booking->id}/cancel");
        $response->assertStatus(422);

        // Cannot confirm completed booking
        $response = $this->postJson("/api/v1/bookings/{$booking->id}/confirm");
        $response->assertStatus(422);

        // Cannot complete completed booking again
        $response = $this->postJson("/api/v1/bookings/{$booking->id}/complete");
        $response->assertStatus(422);
    }

    public function test_past_booking_cannot_be_modified(): void
    {
        $pastBooking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'date' => Carbon::yesterday()->setTime(14, 0),
            'status' => BookingStatusEnum::PENDING,
        ]);

        $this->actingAs($this->provider);

        // Cannot confirm past booking
        $response = $this->postJson("/api/v1/bookings/{$pastBooking->id}/confirm");
        $response->assertStatus(422);

        $this->actingAs($this->client);

        // Cannot cancel past booking
        $response = $this->postJson("/api/v1/bookings/{$pastBooking->id}/cancel");
        $response->assertStatus(422);
    }

    public function test_provider_update_authorization(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::PENDING,
        ]);

        // Wrong provider
        $wrongProvider = User::factory()->create(['role' => Roles::PROVIDER]);
        $this->actingAs($wrongProvider);

        $response = $this->putJson("/api/v1/bookings/{$booking->id}", [
            'provider_notes' => 'Unauthorized notes',
        ]);

        $response->assertStatus(403);
    }

    public function test_client_update_restrictions(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::PENDING,
        ]);

        $this->actingAs($this->client);

        // Client cannot update status
        $response = $this->putJson("/api/v1/bookings/{$booking->id}", [
            'status' => BookingStatusEnum::CONFIRMED->value,
        ]);

        $response->assertStatus(422);

        // Client cannot update provider notes
        $response = $this->putJson("/api/v1/bookings/{$booking->id}", [
            'provider_notes' => 'Client trying to add provider notes',
        ]);

        $response->assertStatus(422);

        // Client can update their own notes
        $response = $this->putJson("/api/v1/bookings/{$booking->id}", [
            'customer_notes' => 'Updated customer notes',
        ]);

        $response->assertStatus(200);
    }

    public function test_booking_constraints_with_multiple_services(): void
    {
        $testDate = Carbon::tomorrow();

        // Create another service from same provider
        $anotherService = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'duration' => 90,
            'price' => 150.00,
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

        // Book first service at 14:00
        Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id, // 60 minutes
            'provider_id' => $this->provider->id,
            'date' => $testDate->copy()->setTime(14, 0),
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->client);

        // Try to book another service at 14:30 (overlaps with first)
        $bookingData = [
            'service_id' => $anotherService->id,
            'date' => $testDate->format('Y-m-d'),
            'time' => '14:30',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['time']);
    }

    public function test_slot_availability_endpoint_validation(): void
    {
        $this->actingAs($this->client);

        // Missing required fields
        $response = $this->getJson('/api/v1/bookings/availability');
        $response->assertStatus(422);

        // Invalid service ID
        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => 99999,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));
        $response->assertStatus(422);

        // Past date
        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => Carbon::yesterday()->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));
        $response->assertStatus(422);

        // Invalid timezone
        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'timezone' => 'Invalid/Zone',
        ]));
        $response->assertStatus(422);
    }

    public function test_check_slot_endpoint_validation(): void
    {
        $this->actingAs($this->client);

        // Missing required fields
        $response = $this->postJson('/api/v1/bookings/check-slot', []);
        $response->assertStatus(422);

        // Past datetime
        $response = $this->postJson('/api/v1/bookings/check-slot', [
            'service_id' => $this->service->id,
            'datetime' => Carbon::yesterday()->toDateTimeString(),
            'timezone' => 'UTC',
        ]);
        $response->assertStatus(422);

        // Invalid service
        $response = $this->postJson('/api/v1/bookings/check-slot', [
            'service_id' => 99999,
            'datetime' => Carbon::tomorrow()->setTime(14, 0)->toDateTimeString(),
            'timezone' => 'UTC',
        ]);
        $response->assertStatus(422);
    }

    public function test_booking_with_invalid_status_enum(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::PENDING,
        ]);

        $this->actingAs($this->provider);

        $response = $this->putJson("/api/v1/bookings/{$booking->id}", [
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422);
    }

    public function test_delete_booking_authorization(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
        ]);

        // Wrong user cannot delete
        $wrongUser = User::factory()->create(['role' => Roles::USER]);
        $this->actingAs($wrongUser);

        $response = $this->deleteJson("/api/v1/bookings/{$booking->id}");
        $response->assertStatus(403);

        // Correct user can delete
        $this->actingAs($this->client);
        $response = $this->deleteJson("/api/v1/bookings/{$booking->id}");
        $response->assertStatus(200);

        // Verify soft deletion
        $this->assertSoftDeleted('bookings', ['id' => $booking->id]);
    }
}
