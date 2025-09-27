<?php

namespace Modules\Auth\Http\Controllers\Api;

use App\Services\ApiResponseService;
use App\Services\LoggingService;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Services\LoginService;

/**
 * @group Auth
 *
 * @description Login a user
 *
 * @param  LoginRequest  $request
 * @param  LoggingService  $loggingService
 * @return JsonResponse
 *
 * @throws \Exception
 */
#[Group('Auth')]
class LoginController
{
    /**
     * Login a user
     *
     * @throws \Exception
     *
     * @bodyParam email string required User email. Example: john@example.com
     * @bodyParam password string required User password. Example: Password@123
     *
     * @response 200 {
     *     "data": {
     *         "id": 1,
     *         "name": "John Doe",
     *         "email": "john@example.com",
     *         "role": "user",
     *         "timezone": "UTC",
     *         "created_at": "2025-09-25T08:40:31.000000Z",
     *         "updated_at": "2025-09-25T08:40:31.000000Z",
     *         "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
     *     },
     *     "message": "User logged in successfully"
     * }
     * @response 401 {
     *     "message": "Invalid credentials"
     * }
     */
    public function __invoke(
        LoginRequest $request,
        LoginService $loginService,
        LoggingService $loggingService,
        ApiResponseService $apiResponseService
    ): JsonResponse {
        $credentials = $request->only('email', 'password');

        $result = $loginService->login($credentials, $request, $loggingService);

        if (! $result) {
            return $apiResponseService->unauthorized('Invalid credentials');
        }

        return $apiResponseService->successResponse(data: $result->resolve(), message: 'User logged in successfully');
    }
}
