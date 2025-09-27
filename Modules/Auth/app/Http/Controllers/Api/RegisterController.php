<?php

namespace Modules\Auth\Http\Controllers\Api;

use App\Services\ApiResponseService;
use App\Services\LoggingService;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Modules\Auth\DTOs\CreateUserDTO;
use Modules\Auth\Http\Requests\RegisterRequest;
use Modules\Auth\Http\Resources\UserResource;
use Modules\Auth\Services\RegisterService;

/**
 * @group Auth
 *
 * @description Register a new user
 *
 * @param  RegisterRequest  $request
 * @param  RegisterService  $registerService
 * @param  LoggingService  $loggingService
 * @return void
 *
 * @throws Exception
 */
#[Group('Auth')]
class RegisterController
{
    /**
     * Register a new user
     *
     * @throws \Exception
     *
     * @group Auth
     *
     * @api {post} /api/auth/register Register a new user
     *
     * @apiName RegisterUser
     *
     * @bodyParam name string required User name. Example: John Doe
     * @bodyParam email string required User email. Must be unique, valid email format, max 254 chars. Example: john@example.com
     * @bodyParam password string required User password. Min 8 characters. Example: Password@123
     * @bodyParam password_confirmation string required Must match password field. Example: Password@123
     * @bodyParam role string required User role. Must be one of: user, provider. Default: user. Example: user
     * @bodyParam timezone string User timezone (default: UTC). Example: Africa/Cairo
     *
     * @response 201 {
     *     "data": {
     *         "id": 1,
     *         "name": "John Doe",
     *         "email": "john@example.com",
     *         "role": "user",
     *         "timezone": "UTC",
     *         "created_at": "2025-09-25T08:40:31.000000Z",
     *         "updated_at": "2025-09-25T08:40:31.000000Z"
     *     },
     *     "message": "User registered successfully"
     * }
     * @response 422 {
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "email": [
     *             "The email has already been taken."
     *         ]
     *     }
     * }
     */
    public function __invoke(RegisterRequest $request,
        RegisterService $registerService,
        LoggingService $loggingService,
        ApiResponseService $apiResponseService): JsonResponse
    {
        $dto = CreateUserDTO::from($request->validated());
        $user = $registerService->register($dto, $loggingService);

        return $apiResponseService->created(new UserResource($user), 'User registered successfully');
    }
}
