<?php

namespace Modules\Booking\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Modules\Auth\Enums\Roles;
use Modules\Auth\Models\User;
use Modules\AvailabilityManagement\Enums\SlotType;
use Modules\AvailabilityManagement\Models\AvailabilityManagement;
use Modules\Booking\Enums\BookingStatusEnum;
use Modules\Booking\Events\BookingConfirmed;
use Modules\Booking\Events\BookingStatusChanged;
use Modules\Booking\Jobs\SendBookingStatusUpdateEmail;
use Modules\Booking\Models\Booking;
use Modules\Service\Models\Service;
use Tests\TestCase;

class ProviderBookingTest extends TestCase
{
    use RefreshDatabase;

    protected User $provider;

    protected User $client;

    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->provider = User::factory()->create(['role' => Roles::PROVIDER]);
        $this->client = User::factory()->create(['role' => Roles::USER]);

        // Create test service
        $this->service = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'duration' => 60,
            'price' => 100.00,
            'name' => 'Provider Service',
            'description' => 'Test service for provider',
        ]);
    }

    public function test_provider_can_view_their_service_bookings(): void
    {
        // Create bookings for provider's service
        $providerBooking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
        ]);

        // Create booking for another provider (should not be visible)
        $otherProvider = User::factory()->create(['role' => Roles::PROVIDER]);
        $otherService = Service::factory()->create(['provider_id' => $otherProvider->id]);
        Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $otherService->id,
            'provider_id' => $otherProvider->id,
        ]);

        $this->actingAs($this->provider);

        $response = $this->getJson('/api/v1/bookings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'links',
                'meta',
            ]);

        // Provider should only see bookings for their services
        $bookingIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($providerBooking->id, $bookingIds);
        $this->assertCount(1, $bookingIds);
    }

    public function test_provider_can_confirm_pending_booking(): void
    {
        Event::fake([
            BookingConfirmed::class,
        ]);
        Queue::fake();

        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::PENDING,
        ]);

        $this->actingAs($this->provider);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/confirm");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Booking confirmed successfully',
                'data' => [
                    'status' => BookingStatusEnum::CONFIRMED->value,
                ],
            ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatusEnum::CONFIRMED->value,
        ]);

        // Verify events were dispatched
        Event::assertDispatched(BookingConfirmed::class);
        // BookingStatusChanged is not faked, so it actually fires and triggers the listener
        Queue::assertPushed(SendBookingStatusUpdateEmail::class);
    }

    public function test_provider_cannot_confirm_already_confirmed_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->provider);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/confirm");

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Booking cannot be confirmed in its current state',
            ]);
    }

    public function test_provider_cannot_confirm_cancelled_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::CANCELLED,
        ]);

        $this->actingAs($this->provider);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/confirm");

        $response->assertStatus(422);
    }

    public function test_provider_can_complete_confirmed_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->provider);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/complete");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Booking completed successfully',
                'data' => [
                    'status' => BookingStatusEnum::COMPLETED->value,
                ],
            ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatusEnum::COMPLETED->value,
        ]);
    }

    public function test_provider_cannot_complete_pending_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::PENDING,
        ]);

        $this->actingAs($this->provider);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/complete");

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Booking cannot be completed in its current state',
            ]);
    }

    public function test_provider_can_cancel_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::PENDING,
        ]);

        $this->actingAs($this->provider);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Booking cancelled successfully',
            ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatusEnum::CANCELLED->value,
        ]);
    }

    public function test_provider_can_add_notes_to_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->provider);

        $response = $this->putJson("/api/v1/bookings/{$booking->id}", [
            'provider_notes' => 'Please bring your own materials',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Booking updated successfully',
                'data' => [
                    'provider_notes' => 'Please bring your own materials',
                ],
            ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'provider_notes' => 'Please bring your own materials',
        ]);
    }

    public function test_provider_cannot_access_other_providers_bookings(): void
    {
        $otherProvider = User::factory()->create(['role' => Roles::PROVIDER]);
        $otherService = Service::factory()->create(['provider_id' => $otherProvider->id]);
        $otherBooking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $otherService->id,
            'provider_id' => $otherProvider->id,
        ]);

        $this->actingAs($this->provider);

        // Try to view other provider's booking
        $response = $this->getJson("/api/v1/bookings/{$otherBooking->id}");
        $response->assertStatus(403);

        // Try to confirm other provider's booking
        $response = $this->postJson("/api/v1/bookings/{$otherBooking->id}/confirm");
        $response->assertStatus(403);
    }

    public function test_provider_can_view_bookings_by_status(): void
    {
        // Create bookings with different statuses
        Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::PENDING,
        ]);

        Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->provider);

        $response = $this->getJson('/api/v1/bookings/status/pending');

        $response->assertStatus(200);

        $statuses = collect($response->json('data'))->pluck('status')->unique();
        $this->assertEquals(['pending'], $statuses->toArray());
    }

    public function test_provider_can_view_availability_for_their_services(): void
    {
        // Create availability slot
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => Carbon::tomorrow()->dayOfWeek,
            'from' => Carbon::tomorrow()->setTime(9, 0),
            'to' => Carbon::tomorrow()->setTime(17, 0),
            'status' => 1,
        ]);

        $this->actingAs($this->provider);

        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'date',
                    'available_slots',
                    'timezone',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.available_slots'));
    }

    public function test_provider_sees_blocked_slots_due_to_existing_bookings(): void
    {
        // Create availability slot
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => Carbon::tomorrow()->dayOfWeek,
            'from' => Carbon::tomorrow()->setTime(9, 0),
            'to' => Carbon::tomorrow()->setTime(17, 0),
            'status' => 1,
        ]);

        // Create existing booking
        Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'date' => Carbon::tomorrow()->setTime(14, 0),
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->provider);

        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200);

        $availableSlots = $response->json('data.available_slots');

        // 14:00 should not be available due to existing booking
        $this->assertNotContains('14:00', $availableSlots);
    }

    public function test_provider_can_filter_bookings_by_date_range(): void
    {
        // Create bookings on different dates
        $todayBooking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'date' => Carbon::today()->setTime(14, 0),
        ]);

        $tomorrowBooking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'date' => Carbon::tomorrow()->setTime(14, 0),
        ]);

        $this->actingAs($this->provider);

        // Filter by today only
        $response = $this->getJson('/api/v1/bookings?'.http_build_query([
            'date_from' => Carbon::today()->format('Y-m-d'),
            'date_to' => Carbon::today()->format('Y-m-d'),
        ]));

        $response->assertStatus(200);

        $bookingIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($todayBooking->id, $bookingIds);
        $this->assertNotContains($tomorrowBooking->id, $bookingIds);
    }

    public function test_provider_status_transitions_validation(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::COMPLETED,
        ]);

        $this->actingAs($this->provider);

        // Try to confirm a completed booking (invalid transition)
        $response = $this->postJson("/api/v1/bookings/{$booking->id}/confirm");

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Booking cannot be confirmed in its current state',
            ]);
    }

    public function test_provider_cannot_complete_cancelled_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::CANCELLED,
        ]);

        $this->actingAs($this->provider);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/complete");

        $response->assertStatus(422);
    }

    public function test_provider_can_update_booking_price(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'status' => BookingStatusEnum::PENDING,
            'price' => 100.00,
        ]);

        $this->actingAs($this->provider);

        $response = $this->putJson("/api/v1/bookings/{$booking->id}", [
            'price' => 150.00,
            'provider_notes' => 'Price adjusted due to additional requirements',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'price' => 150.00,
            'provider_notes' => 'Price adjusted due to additional requirements',
        ]);
    }

    public function test_provider_manages_multiple_overlapping_services(): void
    {
        // Create another service with different duration
        $longService = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'duration' => 120, // 2 hours
            'price' => 200.00,
        ]);

        // Create availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => Carbon::tomorrow()->dayOfWeek,
            'from' => Carbon::tomorrow()->setTime(9, 0),
            'to' => Carbon::tomorrow()->setTime(17, 0),
            'status' => 1,
        ]);

        // Book the long service at 14:00
        Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $longService->id,
            'provider_id' => $this->provider->id,
            'date' => Carbon::tomorrow()->setTime(14, 0),
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->provider);

        // Check availability for the shorter service - should show blocked slots
        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id, // 1-hour service
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200);

        $availableSlots = $response->json('data.available_slots');

        // Slots from 14:00 to 16:00 should be blocked (2-hour booking)
        $this->assertNotContains('14:00', $availableSlots);
        $this->assertNotContains('15:00', $availableSlots);
        $this->assertContains('16:00', $availableSlots); // Should be available after 2-hour booking
    }
}
