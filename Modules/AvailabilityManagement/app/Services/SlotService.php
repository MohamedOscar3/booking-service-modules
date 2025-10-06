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
     * @param Carbon|string|null $from Date to get slots for (expected in user timezone)
     * @return array Available time slots
     */
    protected function getSlots(string $timezone = 'UTC', $from = null): array
    {
        if (! ($from instanceof Carbon)) {
            $from = Carbon::parse($from, $timezone);
        }

        $fromUtc = $from->copy()->utc();
        $fromDay = $from->weekday();
        $currentTimeInUserTz = Carbon::now($timezone);

        $slots = AvailabilityManagement::where('week_day', $fromDay)
            ->where('provider_id', $this->service->provider_id)
            ->get();

        $oneTimeSlots = AvailabilityManagement::where('type', SlotType::once)
            ->where('provider_id', $this->service->provider_id)
            ->where('status', 1)
            ->whereDate('from', $fromUtc->format('Y-m-d'))
            ->get();

        $availableSlots = $slots->merge($oneTimeSlots);

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

        if ($earliestTime === null || $latestTime === null) {
            return [];
        }

        $allDaysSlots = [];

        foreach ($availableSlots as $slot) {
            $slotFrom = Carbon::parse($slot->from);
            $slotTo = Carbon::parse($slot->to);

            $availableMinutes = $slotFrom->diffInMinutes($slotTo);

            if ($availableMinutes < $this->service->duration) {
                continue;
            }

            $lastPossibleStart = $slotTo->copy()->subMinutes($this->service->duration);

            $currentTime = $slotFrom->copy();
            while ($currentTime->lte($lastPossibleStart)) {
                $allDaysSlots[] = $currentTime->format('H:i');
                $currentTime->addMinutes($this->service->duration);
            }
        }

        $nonWorkingBlocks = AvailabilityManagement::where('type', SlotType::once)
            ->where('provider_id', $this->service->provider_id)
            ->where('status', 0)
            ->whereDate('from', $fromUtc->format('Y-m-d'))
            ->get();

        $dayStart = $from->copy()->startOfDay()->utc();
        $dayEnd = $from->copy()->endOfDay()->utc();

        $bookedSlotsForUser = Booking::with('service')
            ->whereNotIn('status', [BookingStatusEnum::CANCELLED])
            ->where('user_id', auth()->id())
            ->where(function ($query) use ($dayStart, $dayEnd) {
                $query->whereBetween('date', [$dayStart->subMinute()->format('Y-m-d H:i'), $dayEnd->addMinute()->format('Y-m-d H:i')]);
            })
            ->get();

        $bookedSlotsForProvider = Booking::with('service')
            ->whereNotIn('status', [BookingStatusEnum::CANCELLED])
            ->where('provider_id', $this->service->provider_id)
            ->where(function ($query) use ($dayStart, $dayEnd) {
                $query->whereBetween('date', [$dayStart->subMinute()->format('Y-m-d H:i'), $dayEnd->addMinute()->format('Y-m-d H:i')]);
            })
            ->get();

        $nonAvailablePeriods = [];

        foreach ($nonWorkingBlocks as $block) {
            $nonAvailablePeriods[] = [
                'from' => Carbon::parse($block->from),
                'to' => Carbon::parse($block->to),
            ];
        }

        foreach ($bookedSlotsForUser as $booking) {
            $bookingFrom = Carbon::parse($booking->date, 'UTC')->setTimezone($timezone);
            $bookingTo = $bookingFrom->copy()->addMinutes($booking->service->duration); // Removed +1 minute

            if ($bookingFrom->isSameDay($from) || $bookingTo->isSameDay($from)) {
                $nonAvailablePeriods[] = [
                    'from' => $bookingFrom,
                    'to' => $bookingTo,
                ];
            }
        }

        foreach ($bookedSlotsForProvider as $booking) {
            $bookingFrom = Carbon::parse($booking->date, 'UTC')->setTimezone($timezone);
            $bookingTo = $bookingFrom->copy()->addMinutes($booking->service->duration); // Removed +1 minute

            if ($bookingFrom->isSameDay($from) || $bookingTo->isSameDay($from)) {
                $nonAvailablePeriods[] = [
                    'from' => $bookingFrom,
                    'to' => $bookingTo,
                ];
            }
        }

        $availableTimeSlots = collect($allDaysSlots)->unique()->filter(function ($slot) use ($nonAvailablePeriods, $currentTimeInUserTz, $from, $timezone) {
            $slotTime = Carbon::parse($from->format('Y-m-d').' '.$slot, $timezone);
            $slotEndTime = $slotTime->copy()->addMinutes($this->service->duration);

            if ($from->isSameDay($currentTimeInUserTz) && $slotTime->lt($currentTimeInUserTz)) {
                return false;
            }

            foreach ($nonAvailablePeriods as $period) {
                if ($slotTime->lt($period['to']) && $slotEndTime->gt($period['from'])) {
                    return false;
                }
            }

            return true;
        });

        return $availableTimeSlots->values()->all();
    }

    public function getAvailableSlots(int $serviceId, string $timezone = 'UTC', $date = null): array
    {
        // Convert the date to a Carbon instance in the user's timezone
        if ($date !== null) {
            $from = Carbon::parse($date, $timezone);
        } else {
            $from = Carbon::now($timezone);
        }

        // Get the service
        $this->service = Service::findOrFail($serviceId);
        $nextWeekRange = CarbonPeriod::create($from->copy(), '1 day', $from->copy()->addWeek());

        $slots = [];
        foreach ($nextWeekRange as $day) {
            // Pass the Carbon instance directly to maintain timezone context
            $slots[$day->format('Y-m-d')] = $this->getSlots($timezone, $day);
        }

        return $slots;
    }
}
