<?php

namespace Modules\AvailabilityManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\AvailabilityManagement\Enums\SlotType;

/**
 * Update AvailabilityManagement request validation
 */
class UpdateAvailabilityManagementRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:'.implode(',', array_column(SlotType::cases(), 'value'))],
            'week_day' => ['required_if:type,recurring', 'nullable','integer', 'min:0', 'max:6'],
            'from' => ['sometimes', ],
            'to' => ['sometimes', 'after:from'],

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
            'type.string' => 'The slot type must be a string.',
            'type.in' => 'The slot type must be either recurring or once.',
            'week_day.integer' => 'The week day must be an integer.',
            'week_day.min' => 'The week day must be at least 0 (Sunday).',
            'week_day.max' => 'The week day must not exceed 6 (Saturday).',
            'from.date_format' => 'The start time must be in HH:MM format.',
            'to.date_format' => 'The end time must be in HH:MM format.',
            'to.after' => 'The end time must be after the start time.',
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
}
