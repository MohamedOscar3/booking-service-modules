<?php

namespace Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Enums\Roles;
use Modules\Booking\Enums\BookingStatusEnum;

class UpdateBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $booking = $this->route('booking');
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Admin can update any booking
        if ($user->role === Roles::ADMIN) {
            return true;
        }

        // Provider can update their own service bookings
        if ($user->role === Roles::PROVIDER && $booking->service->provider_id === $user->id) {
            return true;
        }

        // User can only update their own bookings (limited fields)
        if ($user->role === Roles::USER && $booking->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $booking = $this->route('booking');
        $user = auth()->user();

        $rules = [];

        // If no user is authenticated (e.g., during documentation generation), return basic rules
        if (! $user) {
            return [
                'status' => ['sometimes', 'string'],
                'provider_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'customer_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'price' => ['sometimes', 'numeric', 'min:0'],
            ];
        }

        // Status updates (only providers and admins)
        if ($user->role === Roles::PROVIDER || $user->role === Roles::ADMIN) {
            $rules['status'] = [
                'sometimes',
                'string',
                function ($attribute, $value, $fail) use ($booking) {
                    $newStatus = BookingStatusEnum::tryFrom($value);
                    if (! $newStatus) {
                        $fail('Invalid status provided.');

                        return;
                    }

                    if (! $booking->status->canTransitionTo($newStatus)) {
                        $fail("Cannot change status from {$booking->status->value} to {$newStatus->value}.");
                    }
                },
            ];
        }

        // Provider notes (only providers and admins)
        if ($user->role === Roles::PROVIDER || $user->role === Roles::ADMIN) {
            $rules['provider_notes'] = ['sometimes', 'nullable', 'string', 'max:1000'];
        }

        // Customer notes (customers can update their own notes)
        if ($user->role === Roles::USER && $booking->user_id === $user->id) {
            $rules['customer_notes'] = ['sometimes', 'nullable', 'string', 'max:1000'];

            // Prevent clients from updating restricted fields
            if ($this->has('status')) {
                $rules['status'] = ['prohibited'];
            }
            if ($this->has('provider_notes')) {
                $rules['provider_notes'] = ['prohibited'];
            }
            if ($this->has('price')) {
                $rules['price'] = ['prohibited'];
            }
        }

        // Providers can update price
        if ($user->role === Roles::PROVIDER) {
            $rules['price'] = ['sometimes', 'numeric', 'min:0'];
        }

        // Admins can update any field
        if ($user->role === Roles::ADMIN) {
            $rules['customer_notes'] = ['sometimes', 'nullable', 'string', 'max:1000'];
            $rules['price'] = ['sometimes', 'numeric', 'min:0'];
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'status.string' => 'Status must be a valid string.',
            'status.prohibited' => 'You are not authorized to update the status.',
            'provider_notes.max' => 'Provider notes cannot exceed 1000 characters.',
            'provider_notes.prohibited' => 'You are not authorized to update provider notes.',
            'customer_notes.max' => 'Customer notes cannot exceed 1000 characters.',
            'price.numeric' => 'Price must be a valid number.',
            'price.min' => 'Price cannot be negative.',
            'price.prohibited' => 'You are not authorized to update the price.',
        ];
    }

    /**
     * Get the validated data for updating the booking
     */
    public function getUpdateData(): array
    {
        $validated = $this->validated();
        $updateData = [];

        // Convert status string to enum if provided
        if (isset($validated['status'])) {
            $updateData['status'] = BookingStatusEnum::from($validated['status']);
        }

        // Add other fields directly
        foreach (['provider_notes', 'customer_notes', 'price'] as $field) {
            if (isset($validated[$field])) {
                $updateData[$field] = $validated[$field];
            }
        }

        return $updateData;
    }

    /**
     * Get the body parameters for the API documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'Booking status. Can be: pending, confirmed, completed, cancelled. Role-based access applies',
                'example' => 'confirmed',
            ],
            'provider_notes' => [
                'description' => 'Notes from the provider. Maximum 1000 characters. Provider/Admin only',
                'example' => 'Customer was 10 minutes late',
            ],
            'customer_notes' => [
                'description' => 'Notes from the customer. Maximum 1000 characters. Customer can update their own notes',
                'example' => 'Please call when you arrive',
            ],
            'price' => [
                'description' => 'Updated price for the booking. Must be non-negative. Admin only',
                'example' => 50.00,
            ],
        ];
    }
}
