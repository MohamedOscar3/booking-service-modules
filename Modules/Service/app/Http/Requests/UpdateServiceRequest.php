<?php

namespace Modules\Service\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Service request validation
 */
class UpdateServiceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    // Only apply unique check if name is being updated
                    if ($this->has('name')) {
                        $query = \Modules\Service\Models\Service::where('name', $value)
                            ->where('provider_id', $this->input('provider_id', $this->route('service')->provider_id));

                        // Exclude the current service from the check
                        if ($this->route('service')) {
                            $query->where('id', '!=', $this->route('service')->id);
                        }

                        if ($query->exists()) {
                            $fail('The service name already exists for this provider.');
                        }
                    }
                },
            ],
            'description' => ['sometimes', 'string'],
            'duration' => ['sometimes', 'integer', 'min:1'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
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
            'name.string' => 'The service name must be a string.',
            'name.max' => 'The service name must not exceed 255 characters.',
            'description.string' => 'The service description must be a string.',
            'duration.integer' => 'The service duration must be an integer.',
            'duration.min' => 'The service duration must be at least 1 minute.',
            'price.numeric' => 'The service price must be a number.',
            'price.min' => 'The service price must be at least 0.',
            'category_id.integer' => 'The category ID must be an integer.',
            'category_id.exists' => 'The selected category does not exist.',
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
