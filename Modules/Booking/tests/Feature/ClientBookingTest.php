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
use Modules\Booking\Events\BookingCreated;
use Modules\Booking\Jobs\SendBookingConfirmationEmail;
use Modules\Booking\Models\Booking;
use Modules\Service\Models\Service;
use Tests\TestCase;

class ClientBookingTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected User $provider;

    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->client = User::factory()->create(['role' => Roles::USER]);
        $this->provider = User::factory()->create(['role' => Roles::PROVIDER]);

        // Create test service
        $this->service = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'duration' => 60, // 1 hour service
            'price' => 100.00,
            'name' => 'Test Service',
            'description' => 'Test service description',
        ]);
    }

    public function test_client_can_view_available_slots_for_service(): void
    {
        // Create availability slot for tomorrow
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => Carbon::tomorrow()->dayOfWeek,
            'from' => Carbon::tomorrow()->setTime(9, 0),
            'to' => Carbon::tomorrow()->setTime(17, 0),
            'status' => 1, // Available
        ]);

        $this->actingAs($this->client);

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
            ])
            ->assertJson([
                'status' => true,
                'message' => 'Availability retrieved successfully',
                'data' => [
                    'timezone' => 'UTC',
                ],
            ]);

        // Should have available slots
        $this->assertNotEmpty($response->json('data.available_slots'));
    }

    public function test_client_can_check_specific_slot_availability(): void
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

        $this->actingAs($this->client);

        $checkTime = Carbon::tomorrow()->setTime(14, 0);

        $response = $this->postJson('/api/v1/bookings/check-slot', [
            'service_id' => $this->service->id,
            'datetime' => $checkTime->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'available',
                    'datetime',
                    'timezone',
                ],
            ])
            ->assertJson([
                'status' => true,
                'data' => [
                    'available' => true,
                    'datetime' => $checkTime->toDateTimeString(),
                    'timezone' => 'UTC',
                ],
            ]);
    }

    public function test_client_can_create_booking_for_available_slot(): void
    {
        Event::fake();
        Queue::fake();

        // Create availability slot
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => Carbon::tomorrow()->dayOfWeek,
            'from' => Carbon::tomorrow()->setTime(9, 0),
            'to' => Carbon::tomorrow()->setTime(17, 0),
            'status' => 1,
        ]);

        $this->actingAs($this->client);

        $bookingData = [
            'service_id' => $this->service->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'time' => '14:00',
            'timezone' => 'UTC',
            'customer_notes' => 'Looking forward to this service',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'service_name',
                    'status',
                    'date',
                    'price',
                    'customer_notes',
                ],
            ])
            ->assertJson([
                'status' => true,
                'message' => 'Booking created successfully',
                'data' => [
                    'service_name' => $this->service->name,
                    'status' => BookingStatusEnum::PENDING->value,
                    'price' => $this->service->price,
                    'customer_notes' => 'Looking forward to this service',
                ],
            ]);

        // Verify booking was created in database
        $this->assertDatabaseHas('bookings', [
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'status' => BookingStatusEnum::PENDING->value,
            'customer_notes' => 'Looking forward to this service',
        ]);

        // Verify events and jobs were dispatched
        Event::assertDispatched(BookingCreated::class);
        Queue::assertPushed(SendBookingConfirmationEmail::class);
    }

    public function test_client_cannot_book_occupied_slot(): void
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

        // Create existing booking for the slot
        $bookingTime = Carbon::tomorrow()->setTime(14, 0);
        Booking::factory()->create([
            'user_id' => User::factory()->create()->id,
            'service_id' => $this->service->id,
            'date' => $bookingTime,
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->client);

        $bookingData = [
            'service_id' => $this->service->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'time' => '14:00',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['time']);
    }

    public function test_client_cannot_book_slot_without_availability(): void
    {
        // No availability slots created
        $this->actingAs($this->client);

        $bookingData = [
            'service_id' => $this->service->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'time' => '14:00',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['time']);
    }

    public function test_client_cannot_book_in_the_past(): void
    {
        $this->actingAs($this->client);

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

    public function test_client_cannot_double_book_same_time(): void
    {
        // Create availability slots
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => Carbon::tomorrow()->dayOfWeek,
            'from' => Carbon::tomorrow()->setTime(9, 0),
            'to' => Carbon::tomorrow()->setTime(17, 0),
            'status' => 1,
        ]);

        // Create first booking for the client
        $bookingTime = Carbon::tomorrow()->setTime(14, 0);
        Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => $bookingTime,
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->client);

        // Try to book overlapping time
        $bookingData = [
            'service_id' => $this->service->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'time' => '14:30', // Overlaps with existing booking
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['time']);
    }

    public function test_client_can_view_their_bookings(): void
    {
        // Create bookings for the client
        $clientBooking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
        ]);

        // Create booking for another user (should not be visible)
        $otherUser = User::factory()->create();
        Booking::factory()->create([
            'user_id' => $otherUser->id,
            'service_id' => $this->service->id,
        ]);

        $this->actingAs($this->client);

        $response = $this->getJson('/api/v1/bookings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'links',
                'meta',
            ]);

        // Client should only see their own booking
        $bookingIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($clientBooking->id, $bookingIds);
        $this->assertCount(1, $bookingIds);
    }

    public function test_client_can_cancel_their_pending_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'status' => BookingStatusEnum::PENDING,
        ]);

        $this->actingAs($this->client);

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

    public function test_client_can_cancel_their_confirmed_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->client);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/cancel");

        $response->assertStatus(200);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatusEnum::CANCELLED->value,
        ]);
    }

    public function test_client_cannot_cancel_completed_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'status' => BookingStatusEnum::COMPLETED,
        ]);

        $this->actingAs($this->client);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/cancel");

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Booking cannot be cancelled',
            ]);
    }

    public function test_client_cannot_access_other_users_booking(): void
    {
        $otherUser = User::factory()->create();
        $otherBooking = Booking::factory()->create([
            'user_id' => $otherUser->id,
            'service_id' => $this->service->id,
        ]);

        $this->actingAs($this->client);

        // Try to view other user's booking
        $response = $this->getJson("/api/v1/bookings/{$otherBooking->id}");
        $response->assertStatus(403);

        // Try to cancel other user's booking
        $response = $this->postJson("/api/v1/bookings/{$otherBooking->id}/cancel");
        $response->assertStatus(403);
    }

    public function test_client_can_filter_bookings_by_status(): void
    {
        // Create bookings with different statuses
        Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'status' => BookingStatusEnum::PENDING,
        ]);

        Booking::factory()->create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->client);

        $response = $this->getJson('/api/v1/bookings/status/pending');

        $response->assertStatus(200);

        $statuses = collect($response->json('data'))->pluck('status')->unique();
        $this->assertEquals(['pending'], $statuses->toArray());
    }

    public function test_slots_blocked_by_service_duration(): void
    {
        // Create a 2-hour service
        $longService = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'duration' => 120, // 2 hours
            'price' => 200.00,
        ]);

        // Create availability slot
        AvailabilityManagement::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => SlotType::recurring,
            'week_day' => Carbon::tomorrow()->dayOfWeek,
            'from' => Carbon::tomorrow()->setTime(9, 0),
            'to' => Carbon::tomorrow()->setTime(17, 0),
            'status' => 1,
        ]);

        // Book a 2-hour slot starting at 14:00
        Booking::factory()->create([
            'user_id' => User::factory()->create()->id,
            'service_id' => $longService->id,
            'date' => Carbon::tomorrow()->setTime(14, 0),
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $this->actingAs($this->client);

        // Try to book 1-hour service at 15:00 (should be blocked by 2-hour service)
        $response = $this->postJson('/api/v1/bookings/check-slot', [
            'service_id' => $this->service->id,
            'datetime' => Carbon::tomorrow()->setTime(15, 0)->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'available' => false,
                ],
            ]);
    }
}
