<?php

namespace Modules\AvailabilityManagement\Services;

use App\Services\TimezoneService;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Modules\AvailabilityManagement\Enums\SlotType;
use Modules\AvailabilityManagement\Models\AvailabilityManagement;
use Modules\Booking\Enums\BookingStatusEnum;
use Modules\Booking\Models\Booking;
use Modules\Service\Models\Service;

class SlotService
{
    protected $timezoneService;

    protected Service $service;

    public function __construct(TimezoneService $timezoneService)
    {
        $this->timezoneService = $timezoneService;
    }

    /**
     * Get available time slots for a specific date and service
     *
     * @param string $timezone User's timezone
     * @param Carbon|string|null $from Date to get slots for
     * @return array Available time slots
     */
    protected function getSlots(string $timezone = 'UTC', $from = null): array
    {
        // Ensure $from is a Carbon instance
        if (! ($from instanceof Carbon)) {
            $from = Carbon::parse($from)->timezone($timezone);
        }

        // Convert to UTC for database operations
        $fromUtc = $this->timezoneService->convertToTimezone($from, $timezone);
        // Get day of week (0-6)
        $fromDay = $fromUtc->weekday();

        // Get current time in H:i format
        $currentTime = Carbon::now($timezone)->format('H:i');

        // Get regular slots for this day of week
        $slots = AvailabilityManagement::where('week_day', $fromDay)
            ->where('provider_id', $this->service->provider_id)
            ->get();

        // OneTime Slots - these are additional available slots
        $oneTimeSlots = AvailabilityManagement::where('type', SlotType::once)
            ->where('provider_id', $this->service->provider_id)
            ->where('status', 1) // Status 1 means available
            ->whereDate('from', $fromUtc->format('Y-m-d'))
            ->get();

        // Add OneTime Slots to regular slots
        $availableSlots = $slots->merge($oneTimeSlots);

        // Find the earliest and latest available times
        $earliestTime = null;
        $latestTime = null;

        foreach ($availableSlots as $slot) {
            $slotFrom = Carbon::parse($slot->from);
            $slotTo = Carbon::parse($slot->to);

            if ($earliestTime === null || $slotFrom->lt($earliestTime)) {
                $earliestTime = $slotFrom;
            }

            if ($latestTime === null || $slotTo->gt($latestTime)) {
                $latestTime = $slotTo;
            }
        }

        // If no slots available, return empty array
        if ($earliestTime === null || $latestTime === null) {
            return [];
        }

        // Generate all possible time slots for the day based on service duration
        $allDaysSlots = [];

        foreach ($availableSlots as $slot) {
            $timeSlots = CarbonPeriod::create(
                Carbon::parse($slot->from),
                $this->service->duration.' minutes',
                Carbon::parse($slot->to)
            );

            foreach ($timeSlots as $t) {
                $allDaysSlots[] = $t->format('H:i');
            }
        }

        // Get non-working blocks (status 0 means unavailable)
        $nonWorkingBlocks = AvailabilityManagement::where('type', SlotType::once)
            ->where('provider_id', $this->service->provider_id)
            ->where('status', 0)
            ->whereDate('from', $fromUtc->format('Y-m-d'))
            ->get();

        // Get bookings for this user on this date
        $bookedSlotsForUser = Booking::whereNotIn('status', [BookingStatusEnum::CANCELLED])
            ->where('user_id', auth()->id())
            ->whereDate('date', $fromUtc->format('Y-m-d'))
            ->get();

        // Get bookings for this provider on this date
        $bookedSlotsForProvider = Booking::whereNotIn('status', [BookingStatusEnum::CANCELLED])
            ->where('provider_id', $this->service->provider_id)
            ->where('service_id', $this->service->id)
            ->whereDate('date', $fromUtc->format('Y-m-d'))
            ->get();

        // Collect all non-available periods
        $nonAvailablePeriods = [];

        // Add non-working blocks to non-available periods
        foreach ($nonWorkingBlocks as $block) {
            $nonAvailablePeriods[] = [
                'from' => Carbon::parse($block->from),
                'to' => Carbon::parse($block->to),
            ];
        }

        // Add user bookings to non-available periods
        foreach ($bookedSlotsForUser as $booking) {
            $bookingFrom = Carbon::parse($booking->date);
            $bookingTo = (clone $bookingFrom)->addMinutes($this->service->duration);

            $nonAvailablePeriods[] = [
                'from' => $bookingFrom,
                'to' => $bookingTo,
            ];
        }

        // Add provider bookings to non-available periods
        foreach ($bookedSlotsForProvider as $booking) {
            $bookingFrom = Carbon::parse($booking->date);
            $bookingTo = (clone $bookingFrom)->addMinutes($this->service->duration);

            $nonAvailablePeriods[] = [
                'from' => $bookingFrom,
                'to' => $bookingTo,
            ];
        }

        // Filter available slots
        $availableTimeSlots = collect($allDaysSlots)->filter(function ($slot) use ($nonAvailablePeriods, $currentTime, $fromUtc) {
            // Create a full datetime for the slot
            $slotTime = Carbon::parse($fromUtc->format('Y-m-d').' '.$slot);
            $slotEndTime = (clone $slotTime)->addMinutes($this->service->duration);

            // Check if slot is in the past
            if ($fromUtc->format('Y-m-d') == Carbon::now()->format('Y-m-d') && $slot < $currentTime) {
                return false;
            }

            // Check if slot falls within any non-available period
            foreach ($nonAvailablePeriods as $period) {
                // Check for any overlap between the slot time range and the non-available period
                if (
                    ($slotTime->between($period['from'], $period['to'])) ||
                    ($slotEndTime->between($period['from'], $period['to'])) ||
                    ($period['from']->between($slotTime, $slotEndTime)) ||
                    ($period['to']->between($slotTime, $slotEndTime))
                ) {
                    return false;
                }
            }

            return true;
        });

        return $availableTimeSlots->values()->all();
    }

    public function getAvailableSlots(int $serviceId, string $timezone = 'UTC', $date = null): array
    {
        // Convert the date to a Carbon instance in the user's timezone\
        if ($date != null) {
            $from = Carbon::parse($date)->timezone($timezone);
        } else {
            $from = Carbon::now()->timezone($timezone);
        }

        // Get the service
        $this->service = Service::findOrFail($serviceId);
        $nextWeekRange = CarbonPeriod::create($from->copy(), '1 day', $from->copy()->addWeek());

        $slots = [];
        foreach ($nextWeekRange as $day) {
            $slots[$day->format('Y-m-d')] = $this->getSlots($timezone, $day->format('Y-m-d'));
        }

        return $slots;
    }
}
