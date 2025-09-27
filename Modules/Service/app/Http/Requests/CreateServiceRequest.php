<?php

namespace Modules\Service\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create Service request validation
 */
class CreateServiceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255',
                function ($attribute, $value, $fail) {
                    // Check if service name already exists for the authenticated provider
                    $query = \Modules\Service\Models\Service::where('name', $value)
                        ->where('provider_id', auth()->id());

                    if ($query->exists()) {
                        $fail('The service name already exists for this provider.');
                    }
                },
            ],
            'description' => ['required', 'string'],
            'duration' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
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
            'name.required' => 'The service name is required.',
            'name.string' => 'The service name must be a string.',
            'name.max' => 'The service name must not exceed 255 characters.',
            'description.required' => 'The service description is required.',
            'description.string' => 'The service description must be a string.',
            'duration.required' => 'The service duration is required.',
            'duration.integer' => 'The service duration must be an integer.',
            'duration.min' => 'The service duration must be at least 1 minute.',
            'price.required' => 'The service price is required.',
            'price.numeric' => 'The service price must be a number.',
            'price.min' => 'The service price must be at least 0.',
            'provider_id.required' => 'The provider is required.',
            'provider_id.integer' => 'The provider ID must be an integer.',
            'provider_id.exists' => 'The selected provider does not exist.',
            'category_id.required' => 'The category is required.',
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

    /**
     * Get the body parameters for the API documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Service name. Must be unique for the provider. Maximum 255 characters',
                'example' => 'Hair Cut & Style',
            ],
            'description' => [
                'description' => 'Detailed description of the service',
                'example' => 'Professional hair cutting and styling service',
            ],
            'duration' => [
                'description' => 'Service duration in minutes. Must be at least 1 minute',
                'example' => 60,
            ],
            'price' => [
                'description' => 'Service price in the base currency. Must be non-negative',
                'example' => 25.00,
            ],
            'category_id' => [
                'description' => 'ID of the category this service belongs to. Must exist in categories table',
                'example' => 1,
            ],
            'status' => [
                'description' => 'Service active status. True for active, false for inactive. Optional, defaults to true',
                'example' => true,
            ],
        ];
    }
}
