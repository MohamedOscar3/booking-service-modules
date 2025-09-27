<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Database\Factories\UserFactory;
use Modules\Auth\Models\User;
use Tests\TestCase;

/**
 * @group Auth
 *
 * @testdox RegisterTest
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful user registration
     */
    public function test_user_can_register_successfully()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => 'user',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'timezone',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'data' => [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'role' => $userData['role'],
                    'timezone' => $userData['timezone'],
                ],
                'message' => 'User registered successfully',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => $userData['email'],
            'name' => $userData['name'],
            'role' => $userData['role'],
            'timezone' => $userData['timezone'],
        ]);
    }

    /**
     * Test registration with existing email
     */
    public function test_user_cannot_register_with_existing_email()
    {
        // Create a user first
        $existingUser = UserFactory::new()->create();

        $userData = [
            'name' => 'Jane Doe',
            'email' => $existingUser->email, // Using existing email
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => 'user',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test registration with invalid role
     */
    public function test_user_cannot_register_with_invalid_role()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => 'invalid_role', // Invalid role
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    /**
     * Test registration with password confirmation mismatch
     */
    public function test_user_cannot_register_with_password_mismatch()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'DifferentPassword@123', // Mismatched password
            'role' => 'user',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test registration with missing required fields
     */
    public function test_user_cannot_register_with_missing_required_fields()
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    /**
     * Test registration with short password
     */
    public function test_user_cannot_register_with_short_password()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'short', // Too short
            'password_confirmation' => 'short',
            'role' => 'user',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test registration with invalid email format
     */
    public function test_user_cannot_register_with_invalid_email_format()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'not-an-email', // Invalid email format
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => 'user',
            'timezone' => 'UTC',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
