<?php

namespace Modules\AvailabilityManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\AvailabilityManagement\Enums\SlotType;

/**
 * Create AvailabilityManagement request validation
 */
class CreateAvailabilityManagementRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:'.implode(',', array_column(SlotType::cases(), 'value'))],
            'week_day' => ['required_if:type,recurring', 'nullable', 'integer', 'min:0', 'max:6'],
            'from' => ['required'],
            'to' => ['required', 'after:from'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'The slot type is required.',
            'type.string' => 'The slot type must be a string.',
            'type.in' => 'The slot type must be either recurring or once.',
            'week_day.required' => 'The week day is required.',
            'week_day.integer' => 'The week day must be an integer.',
            'week_day.min' => 'The week day must be at least 0 (Sunday).',
            'week_day.max' => 'The week day must not exceed 6 (Saturday).',
            'from.required' => 'The start time is required.',
            'from.date_format' => 'The start time must be in HH:MM format.',
            'to.required' => 'The end time is required.',
            'to.date_format' => 'The end time must be in HH:MM format.',
            'to.after' => 'The end time must be after the start time.',
            'date.required' => 'The date is required.',
            'date.date' => 'The date must be a valid date.',
            'date.after_or_equal' => 'The date must be today or a future date.',
            'status.boolean' => 'The status must be true or false.',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the body parameters for the API documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'type' => [
                'description' => 'Slot type. Must be either "recurring" for weekly recurring slots or "once" for one-time slots',
                'example' => 'recurring',
            ],
            'week_day' => [
                'description' => 'Day of the week (0-6, where 0=Sunday, 6=Saturday). Required for recurring slots',
                'example' => 1,
            ],
            'from' => [
                'description' => 'Start time in HH:MM format or datetime',
                'example' => '09:00',
            ],
            'to' => [
                'description' => 'End time in HH:MM format or datetime. Must be after start time',
                'example' => '17:00',
            ],
            'status' => [
                'description' => 'Slot active status. True for active, false for inactive. Optional, defaults to true',
                'example' => true,
            ],
        ];
    }
}
