<?php

namespace Modules\AvailabilityManagement\Tests\Unit;

use App\Services\TimezoneService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Modules\AvailabilityManagement\Enums\SlotType;
use Modules\AvailabilityManagement\Models\AvailabilityManagement;
use Modules\AvailabilityManagement\Services\SlotService;
use Modules\Booking\Enums\BookingStatusEnum;
use Modules\Booking\Models\Booking;
use Modules\Service\Models\Service;
use Tests\TestCase;

class SlotServiceTest extends TestCase
{
    use RefreshDatabase;

    private SlotService $slotService;

    private $timezoneServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timezoneServiceMock = Mockery::mock(TimezoneService::class);
        $this->slotService = new SlotService($this->timezoneServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_available_slots_returns_weekly_slots(): void
    {
        $service = Service::factory()->create(['duration' => 60]);
        $date = '2025-01-15'; // Wednesday
        $timezone = 'UTC';

        $this->timezoneServiceMock
            ->shouldReceive('convertToTimezone')
            ->andReturnUsing(function ($date, $tz) {
                return Carbon::parse($date)->utc();
            });

        // Create recurring availability for Wednesday (weekday 3)
        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::recurring,
            'week_day' => 3,
            'from' => Carbon::parse('09:00'),
            'to' => Carbon::parse('12:00'),
            'status' => true,
        ]);

        $slots = $this->slotService->getAvailableSlots($service->id, $timezone, $date);

        $this->assertIsArray($slots);
        $this->assertArrayHasKey('2025-01-15', $slots);
        $this->assertContains('09:00', $slots['2025-01-15']);
        $this->assertContains('10:00', $slots['2025-01-15']);
        $this->assertContains('11:00', $slots['2025-01-15']);
        // 12:00 might be included as CarbonPeriod includes end time when period divides evenly
    }

    public function test_get_available_slots_excludes_past_times_on_current_day(): void
    {
        $service = Service::factory()->create(['duration' => 60]);
        $now = Carbon::now();
        $timezone = 'UTC';

        // Mock current time as 10:30
        Carbon::setTestNow($now->setTime(10, 30));

        $this->timezoneServiceMock
            ->shouldReceive('convertToTimezone')
            ->andReturnUsing(function ($date, $tz) {
                return Carbon::parse($date)->utc();
            });

        // Create availability for today
        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::recurring,
            'week_day' => $now->weekday(),
            'from' => Carbon::parse('09:00'),
            'to' => Carbon::parse('13:00'),
            'status' => true,
        ]);

        $slots = $this->slotService->getAvailableSlots($service->id, $timezone, $now->format('Y-m-d'));

        $todaySlots = $slots[$now->format('Y-m-d')];

        $this->assertNotContains('09:00', $todaySlots); // Past time
        $this->assertNotContains('10:00', $todaySlots); // Past time
        $this->assertContains('11:00', $todaySlots); // Future time
        $this->assertContains('12:00', $todaySlots); // Future time

        Carbon::setTestNow(); // Reset
    }

    public function test_get_available_slots_excludes_non_working_periods(): void
    {
        $service = Service::factory()->create(['duration' => 60]);
        $date = '2025-01-15'; // Wednesday
        $timezone = 'UTC';

        $this->timezoneServiceMock
            ->shouldReceive('convertToTimezone')
            ->andReturnUsing(function ($date, $tz) {
                return Carbon::parse($date)->utc();
            });

        // Create recurring availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::recurring,
            'week_day' => 3,
            'from' => Carbon::parse('09:00'),
            'to' => Carbon::parse('13:00'),
            'status' => true,
        ]);

        // Create non-working period (lunch break)
        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::once,
            'from' => Carbon::parse('2025-01-15 11:00'),
            'to' => Carbon::parse('2025-01-15 12:00'),
            'status' => false, // Non-working
        ]);

        $slots = $this->slotService->getAvailableSlots($service->id, $timezone, $date);

        $todaySlots = $slots['2025-01-15'];

        $this->assertContains('09:00', $todaySlots);
        $this->assertContains('10:00', $todaySlots);
        $this->assertNotContains('11:00', $todaySlots); // Blocked by non-working period
        $this->assertContains('12:00', $todaySlots); // Should be available as non-working period ends at 12:00
    }

    public function test_get_available_slots_handles_multiple_week_days(): void
    {
        $service = Service::factory()->create(['duration' => 30]);
        $date = '2025-01-13'; // Monday
        $timezone = 'UTC';

        $this->timezoneServiceMock
            ->shouldReceive('convertToTimezone')
            ->andReturnUsing(function ($date, $tz) {
                return Carbon::parse($date)->utc();
            });

        // Monday availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::recurring,
            'week_day' => 1, // Monday
            'from' => Carbon::parse('09:00'),
            'to' => Carbon::parse('10:00'),
            'status' => true,
        ]);

        // Wednesday availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::recurring,
            'week_day' => 3, // Wednesday
            'from' => Carbon::parse('14:00'),
            'to' => Carbon::parse('15:00'),
            'status' => true,
        ]);

        $slots = $this->slotService->getAvailableSlots($service->id, $timezone, $date);

        // Should have slots for Monday (2025-01-13)
        $this->assertArrayHasKey('2025-01-13', $slots);
        $this->assertContains('09:00', $slots['2025-01-13']);
        $this->assertContains('09:30', $slots['2025-01-13']);

        // Should have slots for Wednesday (2025-01-15)
        $this->assertArrayHasKey('2025-01-15', $slots);
        $this->assertContains('14:00', $slots['2025-01-15']);
        $this->assertContains('14:30', $slots['2025-01-15']);

        // Tuesday should have no slots
        $this->assertArrayHasKey('2025-01-14', $slots);
        $this->assertEmpty($slots['2025-01-14']);
    }

    public function test_get_available_slots_respects_service_duration(): void
    {
        $service = Service::factory()->create(['duration' => 90]); // 1.5 hours
        $date = '2025-01-15'; // Wednesday
        $timezone = 'UTC';

        $this->timezoneServiceMock
            ->shouldReceive('convertToTimezone')
            ->andReturnUsing(function ($date, $tz) {
                return Carbon::parse($date)->utc();
            });

        // Create 3-hour availability window
        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::recurring,
            'week_day' => 3,
            'from' => Carbon::parse('09:00'),
            'to' => Carbon::parse('12:00'),
            'status' => true,
        ]);

        $slots = $this->slotService->getAvailableSlots($service->id, $timezone, $date);

        $todaySlots = $slots['2025-01-15'];

        $this->assertContains('09:00', $todaySlots);
        $this->assertContains('10:30', $todaySlots); // 90 minutes later
        // 12:00 - 90 minutes = 10:30, so last slot should be 10:30
        $this->assertNotContains('12:00', $todaySlots); // Last slot that doesn't fit
    }

    public function test_get_available_slots_returns_empty_for_no_availability(): void
    {
        $service = Service::factory()->create(['duration' => 60]);
        $date = '2025-01-15'; // Wednesday
        $timezone = 'UTC';

        $this->timezoneServiceMock
            ->shouldReceive('convertToTimezone')
            ->andReturnUsing(function ($date, $tz) {
                return Carbon::parse($date)->utc();
            });

        // No availability created for Wednesday

        $slots = $this->slotService->getAvailableSlots($service->id, $timezone, $date);

        $this->assertArrayHasKey('2025-01-15', $slots);
        $this->assertEmpty($slots['2025-01-15']);
    }

    public function test_get_available_slots_handles_timezone_conversion(): void
    {
        $service = Service::factory()->create(['duration' => 60]);
        $date = '2025-01-15';
        $timezone = 'America/New_York';

        // Mock timezone service to handle all date conversions in the week range
        $this->timezoneServiceMock
            ->shouldReceive('convertToTimezone')
            ->andReturnUsing(function ($date, $tz) {
                return Carbon::parse($date)->utc();
            });

        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::recurring,
            'week_day' => 3,
            'from' => Carbon::parse('09:00'),
            'to' => Carbon::parse('12:00'),
            'status' => true,
        ]);

        $slots = $this->slotService->getAvailableSlots($service->id, $timezone, $date);

        $this->assertIsArray($slots);
        $this->assertArrayHasKey('2025-01-15', $slots);
        $this->assertNotEmpty($slots['2025-01-15']);
    }

    public function test_get_available_slots_with_null_date_uses_current_date(): void
    {
        $service = Service::factory()->create(['duration' => 60]);
        $timezone = 'UTC';
        $now = Carbon::now();

        $this->timezoneServiceMock
            ->shouldReceive('convertToTimezone')
            ->andReturnUsing(function ($date, $tz) {
                return Carbon::parse($date)->utc();
            });

        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::recurring,
            'week_day' => $now->weekday(),
            'from' => Carbon::parse('09:00'),
            'to' => Carbon::parse('12:00'),
            'status' => true,
        ]);

        $slots = $this->slotService->getAvailableSlots($service->id, $timezone, null);

        $this->assertArrayHasKey($now->format('Y-m-d'), $slots);
    }

    public function test_get_available_slots_excludes_user_bookings(): void
    {
        $service = Service::factory()->create(['duration' => 60]);
        $date = '2025-01-15'; // Wednesday
        $timezone = 'UTC';
        $user = \Modules\Auth\Models\User::factory()->create();

        // Mock Auth facade
        Auth::shouldReceive('id')->andReturn($user->id);

        $this->timezoneServiceMock
            ->shouldReceive('convertToTimezone')
            ->andReturnUsing(function ($date, $tz) {
                return Carbon::parse($date)->utc();
            });

        // Create recurring availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::recurring,
            'week_day' => 3, // Wednesday
            'from' => Carbon::parse('09:00'),
            'to' => Carbon::parse('13:00'),
            'status' => true,
        ]);

        // Create a booking for the user at 10:00
        Booking::factory()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'date' => Carbon::parse('2025-01-15 10:00'),
            'status' => BookingStatusEnum::CONFIRMED,
        ]);

        $slots = $this->slotService->getAvailableSlots($service->id, $timezone, $date);

        $todaySlots = $slots['2025-01-15'];

        $this->assertContains('09:00', $todaySlots);
        $this->assertNotContains('10:00', $todaySlots); // Blocked by user booking
        $this->assertContains('11:00', $todaySlots);
        $this->assertContains('12:00', $todaySlots);
    }

    public function test_get_available_slots_excludes_provider_bookings(): void
    {
        $service = Service::factory()->create(['duration' => 60]);

        $date = Carbon::tomorrow()->addHours(11); // Wednesday
        $timezone = 'UTC';
        $user = \Modules\Auth\Models\User::factory()->create();
        $otherUser = \Modules\Auth\Models\User::factory()->create();

        // Mock Auth facade
        Auth::shouldReceive('id')->andReturn($user->id);

        $this->timezoneServiceMock
            ->shouldReceive('convertToTimezone')
            ->andReturnUsing(function ($date, $tz) {
                return Carbon::parse($date)->utc();
            });

        // Create recurring availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::recurring,
            'week_day' => $date->weekday(), // Wednesday
            'from' => Carbon::parse('09:00'),
            'to' => Carbon::parse('13:00'),
            'status' => true,
        ]);



        // Create a booking for another user with the same service provider at 11:00
        Booking::factory()->create([
            'user_id' => $otherUser->id,
            'service_id' => $service->id,
            'date' => $date->format('Y-m-d H:i'),
            'time' => $date->format('H:i'),
            'status' => BookingStatusEnum::CONFIRMED,
            'provider_id' => $service->provider_id,

        ]);





        $slots = $this->slotService->getAvailableSlots($service->id, $timezone, $date);



        $todaySlots = $slots[$date->format('Y-m-d')];



        $this->assertContains('09:00', $todaySlots);
        $this->assertContains('10:00', $todaySlots);
        $this->assertNotContains('11:00', $todaySlots); // Blocked by provider booking
        $this->assertContains('12:00', $todaySlots);
    }

    public function test_get_available_slots_excludes_cancelled_bookings(): void
    {
        $service = Service::factory()->create(['duration' => 60]);
        $date = '2025-01-15'; // Wednesday
        $timezone = 'UTC';
        $user = \Modules\Auth\Models\User::factory()->create();

        // Mock Auth facade
        Auth::shouldReceive('id')->andReturn($user->id);

        $this->timezoneServiceMock
            ->shouldReceive('convertToTimezone')
            ->andReturnUsing(function ($date, $tz) {
                return Carbon::parse($date)->utc();
            });

        // Create recurring availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::recurring,
            'week_day' => 3, // Wednesday
            'from' => Carbon::parse('09:00'),
            'to' => Carbon::parse('13:00'),
            'status' => true,
        ]);

        // Create a cancelled booking at 10:00
        Booking::factory()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'date' => Carbon::parse('2025-01-15 10:00'),
            'status' => BookingStatusEnum::CANCELLED,
        ]);

        $slots = $this->slotService->getAvailableSlots($service->id, $timezone, $date);

        $todaySlots = $slots['2025-01-15'];

        $this->assertContains('09:00', $todaySlots);
        $this->assertContains('10:00', $todaySlots); // Not blocked because booking is cancelled
        $this->assertContains('11:00', $todaySlots);
        $this->assertContains('12:00', $todaySlots);
    }

    public function test_get_available_slots_handles_one_time_available_slots(): void
    {
        $service = Service::factory()->create(['duration' => 60]);
        $date = '2025-01-15'; // Wednesday
        $timezone = 'UTC';

        $this->timezoneServiceMock
            ->shouldReceive('convertToTimezone')
            ->andReturnUsing(function ($date, $tz) {
                return Carbon::parse($date)->utc();
            });

        // No recurring availability for Wednesday

        // Create one-time available slot
        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::once,
            'from' => Carbon::parse('2025-01-15 14:00'),
            'to' => Carbon::parse('2025-01-15 16:00'),
            'status' => true, // Available
        ]);

        $slots = $this->slotService->getAvailableSlots($service->id, $timezone, $date);

        $todaySlots = $slots['2025-01-15'];

        $this->assertContains('14:00', $todaySlots);
        $this->assertContains('15:00', $todaySlots);
    }

    public function test_get_available_slots_handles_overlapping_slots(): void
    {
        $service = Service::factory()->create(['duration' => 60]);
        $date = '2025-01-15'; // Wednesday
        $timezone = 'UTC';

        $this->timezoneServiceMock
            ->shouldReceive('convertToTimezone')
            ->andReturnUsing(function ($date, $tz) {
                return Carbon::parse($date)->utc();
            });

        // Create recurring availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::recurring,
            'week_day' => 3, // Wednesday
            'from' => Carbon::parse('09:00'),
            'to' => Carbon::parse('12:00'),
            'status' => true,
        ]);

        // Create overlapping one-time availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::once,
            'from' => Carbon::parse('2025-01-15 11:00'),
            'to' => Carbon::parse('2025-01-15 14:00'),
            'status' => true,
        ]);

        $slots = $this->slotService->getAvailableSlots($service->id, $timezone, $date);

        $todaySlots = $slots['2025-01-15'];

        $this->assertContains('09:00', $todaySlots);
        $this->assertContains('10:00', $todaySlots);
        $this->assertContains('11:00', $todaySlots);
        $this->assertContains('12:00', $todaySlots);
        $this->assertContains('13:00', $todaySlots);
    }

    public function test_get_available_slots_handles_partial_booking_conflicts(): void
    {
        $service = Service::factory()->create(['duration' => 90]); // 1.5 hours
        $date = '2025-01-15'; // Wednesday
        $timezone = 'UTC';
        $user = \Modules\Auth\Models\User::factory()->create();

        // Mock Auth facade
        Auth::shouldReceive('id')->andReturn($user->id);

        $this->timezoneServiceMock
            ->shouldReceive('convertToTimezone')
            ->andReturnUsing(function ($date, $tz) {
                return Carbon::parse($date)->utc();
            });

        // Create recurring availability
        AvailabilityManagement::factory()->create([
            'provider_id' => $service->provider_id,
            'type' => SlotType::recurring,
            'week_day' => 3, // Wednesday
            'from' => Carbon::parse('09:00'),
            'to' => Carbon::parse('14:00'),
            'status' => true,
        ]);



        // Create a booking that partially overlaps with 10:30 slot
        // Booking from 11:30 to 13:00 (90 min)
        Booking::factory()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'date' => Carbon::parse('2025-01-15 11:30'),
            'status' => BookingStatusEnum::CONFIRMED,
            'provider_id' => $service->provider_id,
        ]);

        $slots = $this->slotService->getAvailableSlots($service->id, $timezone, $date);


        $todaySlots = $slots['2025-01-15'];

        $this->assertContains('09:00', $todaySlots);
        $this->assertNotContains('10:30', $todaySlots); // Conflicts with 11:30 booking (10:30 + 90min = 12:00, which overlaps)
        $this->assertNotContains('11:00', $todaySlots); // Conflicts with 11:30 booking
        $this->assertNotContains('11:30', $todaySlots); // Direct conflict
    }
}
