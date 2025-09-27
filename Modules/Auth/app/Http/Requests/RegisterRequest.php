<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Enums\Roles;

class RegisterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array[]
     */
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'email' => ['required', 'unique:users,email', 'email', 'max:254'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role' => ['required', 'in:'.implode(',', [Roles::USER->value, Roles::PROVIDER->value])],
            'timezone' => ['string'],
        ];
    }

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
                'description' => 'User full name',
                'example' => 'John Doe',
            ],
            'email' => [
                'description' => 'User email address. Must be unique and valid email format, max 254 characters',
                'example' => 'john@example.com',
            ],
            'password' => [
                'description' => 'User password. Must be at least 8 characters long',
                'example' => 'Password@123',
            ],
            'password_confirmation' => [
                'description' => 'Password confirmation. Must match the password field',
                'example' => 'Password@123',
            ],
            'role' => [
                'description' => 'User role. Must be either "user" or "provider"',
                'example' => 'user',
            ],
            'timezone' => [
                'description' => 'User timezone. Optional, defaults to UTC',
                'example' => 'Africa/Cairo',
            ],
        ];
    }
}
