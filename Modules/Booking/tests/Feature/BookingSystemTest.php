<?php

namespace Modules\Booking\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Modules\Auth\Models\User;
use Modules\Booking\Enums\BookingStatusEnum;
use Modules\Booking\Events\BookingCreated;
use Modules\Booking\Jobs\SendBookingConfirmationEmail;
use Modules\Booking\Models\Booking;
use Modules\Service\Models\Service;
use Tests\TestCase;

class BookingSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $provider;

    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->customer = User::factory()->create();
        $this->provider = User::factory()->create();

        // Create test service
        $this->service = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'duration' => 60, // 1 hour service
            'price' => 100.00,
        ]);
    }

    public function test_customer_can_create_booking_with_valid_data(): void
    {
        Event::fake();
        Queue::fake();

        $this->actingAs($this->customer);

        $bookingData = [
            'service_id' => $this->service->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'time' => '14:00',
            'timezone' => 'UTC',
            'customer_notes' => 'Looking forward to the service',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'service_name',
                    'status',
                    'date',
                    'price',
                ],
            ]);

        // Verify booking was created in database
        $this->assertDatabaseHas('bookings', [
            'user_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'status' => BookingStatusEnum::PENDING->value,
            'customer_notes' => 'Looking forward to the service',
        ]);

        // Verify event was dispatched
        Event::assertDispatched(BookingCreated::class);

        // Verify notification jobs were queued
        Queue::assertPushed(SendBookingConfirmationEmail::class);
    }

    public function test_cannot_book_slot_in_the_past(): void
    {
        $this->actingAs($this->customer);

        $bookingData = [
            'service_id' => $this->service->id,
            'date' => Carbon::yesterday()->format('Y-m-d'),
            'time' => '14:00',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_cannot_double_book_same_slot(): void
    {
        $this->actingAs($this->customer);

        $bookingDateTime = Carbon::tomorrow()->setTime(14, 0);

        // Create first booking
        Booking::factory()->create([
            'user_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'date' => $bookingDateTime,
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        // Try to book the same slot
        $bookingData = [
            'service_id' => $this->service->id,
            'date' => $bookingDateTime->format('Y-m-d'),
            'time' => $bookingDateTime->format('H:i'),
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['time']);
    }

    public function test_provider_can_confirm_pending_booking(): void
    {
        $this->actingAs($this->provider);

        $booking = Booking::factory()->create([
            'user_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'status' => BookingStatusEnum::PENDING,
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/confirm");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Booking confirmed successfully',
                'data' => [
                    'status' => BookingStatusEnum::CONFIRMED->value,
                ],
            ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatusEnum::CONFIRMED->value,
        ]);
    }

    public function test_customer_can_cancel_their_booking(): void
    {
        $this->actingAs($this->customer);

        $booking = Booking::factory()->create([
            'user_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'status' => BookingStatusEnum::PENDING,
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/cancel");

        $response->assertStatus(200);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatusEnum::CANCELLED->value,
        ]);
    }

    public function test_cannot_confirm_cancelled_booking(): void
    {
        $this->actingAs($this->provider);

        $booking = Booking::factory()->create([
            'user_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'status' => BookingStatusEnum::CANCELLED,
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/confirm");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Booking cannot be confirmed in its current state',
            ]);
    }

    public function test_can_get_real_time_availability(): void
    {
        $this->actingAs($this->customer);

        $response = $this->getJson('/api/v1/bookings/availability?'.http_build_query([
            'service_id' => $this->service->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'date',
                    'available_slots',
                    'timezone',
                ],
            ]);
    }

    public function test_can_check_specific_slot_availability(): void
    {
        $this->actingAs($this->customer);

        $tomorrow = Carbon::tomorrow()->setTime(14, 0);

        $response = $this->postJson('/api/v1/bookings/check-slot', [
            'service_id' => $this->service->id,
            'datetime' => $tomorrow->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'available',
                    'datetime',
                    'timezone',
                ],
            ]);
    }

    public function test_status_transitions_are_validated(): void
    {
        $this->actingAs($this->provider);

        $booking = Booking::factory()->create([
            'user_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'status' => BookingStatusEnum::COMPLETED,
        ]);

        // Try to confirm a completed booking (invalid transition)
        $response = $this->postJson("/api/v1/bookings/{$booking->id}/confirm");

        $response->assertStatus(422);
    }

    public function test_bookings_are_filtered_by_role(): void
    {
        // Create bookings for different users and services
        $otherUser = User::factory()->create();
        $otherService = Service::factory()->create();

        $customerBooking = Booking::factory()->create([
            'user_id' => $this->customer->id,
            'service_id' => $this->service->id,
        ]);

        $otherBooking = Booking::factory()->create([
            'user_id' => $otherUser->id,
            'service_id' => $otherService->id,
        ]);

        // Customer should only see their own bookings
        $this->actingAs($this->customer);
        $response = $this->getJson('/api/v1/bookings');

        $response->assertStatus(200);
        $bookingIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($customerBooking->id, $bookingIds);
        $this->assertNotContains($otherBooking->id, $bookingIds);
    }

    public function test_soft_deletion_works(): void
    {
        $this->actingAs($this->customer);

        $booking = Booking::factory()->create([
            'user_id' => $this->customer->id,
            'service_id' => $this->service->id,
        ]);

        $response = $this->deleteJson("/api/v1/bookings/{$booking->id}");

        $response->assertStatus(200);

        // Booking should be soft deleted
        $this->assertSoftDeleted('bookings', ['id' => $booking->id]);
    }
}
