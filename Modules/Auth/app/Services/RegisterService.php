<?php

namespace Modules\Auth\Services;

use App\Services\LoggingService;
use Modules\Auth\DTOs\CreateUserDTO;
use Modules\Auth\Models\User;

class RegisterService
{
    /**
     * @group Auth
     * @description Register a new user
     *
     * @throws \Exception
     */
    public function register(CreateUserDTO $dto, LoggingService $loggingService): User
    {
        try {
            $user = new User;
            $user->name = $dto->name;
            $user->email = $dto->email;
            $user->role = $dto->role;
            $user->password = $dto->password;
            $user->timezone = $dto->timezone;
            $user->save();

            return $user;
        } catch (\Exception $e) {
            $loggingService->log('Error creating user', ['error' => $e->getMessage(), 'line' => $e->getLine()]);
            throw new \Exception('Error creating user');
        }
    }
}
