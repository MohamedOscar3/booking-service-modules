<?php

namespace Modules\Booking\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Booking\Enums\BookingStatusEnum;
use Modules\Booking\Services\BookingService;

class CreateBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', 'string', 'timezone'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'service_id.required' => 'Please select a service.',
            'service_id.exists' => 'The selected service is invalid.',
            'date.required' => 'Please select a booking date.',
            'date.after' => 'Booking date must be in the future.',
            'time.required' => 'Please select a booking time.',
            'time.date_format' => 'Time must be in HH:MM format.',
            'timezone.required' => 'Timezone is required.',
            'timezone.timezone' => 'Invalid timezone provided.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateBusinessRules($validator);
            $this->validateAvailability($validator);
        });
    }

    /**
     * Validate booking availability
     */
    protected function validateAvailability($validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return; // Skip availability check if basic validation fails
        }

        $serviceId = $this->input('service_id');
        $date = $this->input('date');
        $time = $this->input('time');
        $timezone = $this->input('timezone', 'UTC');

        $dateTime = Carbon::parse("$date $time", $timezone);
        $bookingService = app(BookingService::class);

        // Check if time slot is available
        if (! $bookingService->isTimeSlotAvailable($serviceId, $dateTime->toDateTimeString(), $timezone)) {
            $validator->errors()->add('time', 'This time slot is not available.');
        }

        // Check for double booking
        if ($bookingService->isSlotOccupied($serviceId, $dateTime)) {
            $validator->errors()->add('time', 'This time slot is already occupied.');
        }

        // Check user doesn't have overlapping booking
        if ($bookingService->userHasOverlappingBooking(auth()->id(), $serviceId, $dateTime)) {
            $validator->errors()->add('time', 'You already have a booking during this time.');
        }
    }

    /**
     * Validate business rules
     */
    protected function validateBusinessRules($validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $serviceId = $this->input('service_id');
        $service = \Modules\Service\Models\Service::find($serviceId);

        // Prevent users from booking their own services
        if ($service && $service->provider_id === auth()->id()) {
            $validator->errors()->add('service_id', 'You cannot book your own service.');

            return;
        }

        $date = $this->input('date');
        $time = $this->input('time');
        $timezone = $this->input('timezone', 'UTC');

        $dateTime = Carbon::parse("$date $time", $timezone);

        // Ensure booking is not in the past
        if ($dateTime->isPast()) {
            $validator->errors()->add('date', 'Cannot book appointments in the past.');
        }

        // Ensure booking is not too far in the future (optional business rule)
        $maxAdvanceBooking = Carbon::now($timezone)->addMonths(6)->addDays(1);
        if ($dateTime->gt($maxAdvanceBooking)) {
            $validator->errors()->add('date', 'Cannot book more than 6 months in advance.');
        }
    }

    /**
     * Get the validated data as a DTO-compatible array
     */
    public function getBookingData(): array
    {
        $validated = $this->validated();
        $serviceId = $validated['service_id'];
        $service = \Modules\Service\Models\Service::findOrFail($serviceId);

        $dateTime = Carbon::parse($validated['date'].' '.$validated['time'], $validated['timezone']);

        // Find appropriate availability slot
        $slotId = $this->findAvailabilitySlot($service->provider_id, $dateTime);

        return [
            'user_id' => auth()->id(),
            'service_id' => $serviceId,
            'provider_id' => $service->provider_id,
            'service_name' => $service->name,
            'service_description' => $service->description,
            'price' => $service->price,
            'date' => $dateTime->utc(),
            'time' => $validated['time'],
            'status' => BookingStatusEnum::PENDING->value,
            'customer_notes' => $validated['customer_notes'] ?? null,
            'slot_id' => $slotId,
        ];
    }

    /**
     * Find the availability slot that matches the booking datetime
     */
    protected function findAvailabilitySlot(int $providerId, Carbon $dateTime): ?int
    {
        $weekDay = $dateTime->dayOfWeek; // 0=Sunday, 1=Monday, etc.
        $timeString = $dateTime->format('H:i');

        // Get all slots for this provider and day
        $slots = \Modules\AvailabilityManagement\Models\AvailabilityManagement::where('provider_id', $providerId)
            ->where('week_day', $weekDay)
            ->where('status', 1) // Active slots only
            ->get();

        foreach ($slots as $slot) {
            // Extract time from 'from' and 'to' fields (handle both datetime and time formats)
            $fromTime = $this->extractTimeFromString($slot->from);
            $toTime = $this->extractTimeFromString($slot->to);

            if ($fromTime <= $timeString && $toTime > $timeString) {
                return $slot->id;
            }
        }

        return null;
    }

    /**
     * Extract time (H:i) from either datetime or time string
     */
    protected function extractTimeFromString(string $timeStr): string
    {
        // If it's already in H:i format, return as is
        if (preg_match('/^\d{2}:\d{2}$/', $timeStr)) {
            return $timeStr;
        }

        // If it's a datetime, extract the time part
        try {
            return Carbon::parse($timeStr)->format('H:i');
        } catch (\Exception $e) {
            // Fallback - try to extract time pattern from string
            if (preg_match('/(\d{2}:\d{2})/', $timeStr, $matches)) {
                return $matches[1];
            }

            return '00:00'; // Default fallback
        }
    }

    /**
     * Get the body parameters for the API documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'service_id' => [
                'description' => 'ID of the service to book. Must exist in services table',
                'example' => 1,
            ],
            'date' => [
                'description' => 'Date for the booking in YYYY-MM-DD format. Must be in the future',
                'example' => '2024-12-30',
            ],
            'time' => [
                'description' => 'Time for the booking in HH:MM format (24-hour)',
                'example' => '14:30',
            ],
            'customer_notes' => [
                'description' => 'Optional notes from the customer. Maximum 1000 characters',
                'example' => 'Please call when you arrive',
            ],
            'timezone' => [
                'description' => 'Timezone for the booking. Must be a valid timezone identifier',
                'example' => 'Africa/Cairo',
            ],
        ];
    }
}
