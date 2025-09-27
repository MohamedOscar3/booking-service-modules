<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

/**
 * @group Auth
 *
 * @testdox LoginTest
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful user login
     */
    public function test_user_can_login_successfully()
    {
        // Create a user
        $user = UserFactory::new()->create([
            'email' => 'test@example.com',
            'password' => 'Password@123',
        ]);

        $loginData = [
            'email' => 'test@example.com',
            'password' => 'Password@123',
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'timezone',
                    'created_at',
                    'updated_at',
                    'token',
                ],
                'message',
            ])
            ->assertJson([
                'data' => [
                    'email' => $user->email,
                ],
                'message' => 'User logged in successfully',
            ]);
    }

    /**
     * Test login with invalid credentials
     */
    public function test_user_cannot_login_with_invalid_credentials()
    {
        // Create a user
        UserFactory::new()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('Password@123'),
        ]);

        $loginData = [
            'email' => 'test@example.com',
            'password' => 'WrongPassword',
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials',
            ]);
    }

    /**
     * Test login with non-existent email
     */
    public function test_user_cannot_login_with_nonexistent_email()
    {
        $loginData = [
            'email' => 'nonexistent@example.com',
            'password' => 'Password@123',
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials',
            ]);
    }

    /**
     * Test login with invalid email format
     */
    public function test_user_cannot_login_with_invalid_email_format()
    {
        $loginData = [
            'email' => 'not-an-email',
            'password' => 'Password@123',
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test login with missing required fields
     */
    public function test_user_cannot_login_with_missing_required_fields()
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
