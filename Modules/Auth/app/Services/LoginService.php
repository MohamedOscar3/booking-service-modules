<?php

namespace Modules\Auth\Services;

use App\Services\LoggingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Http\Resources\UserResource;
use Modules\Auth\Models\User;

class LoginService
{
    /**
     * @group Auth
     * @description Login a user
     */
    public function login(array $credentials, Request $request, LoggingService $loggingService): ?UserResource
    {
        if (! Auth::attempt($credentials)) {
            $loggingService->log('Login failed for email: '.$request->email, [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return null;
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        $loggingService->log('User logged in: '.$user->email, [
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        session(['timezone' => $user->timezone]);

        $userData = new UserResource($user);

        return $userData->addToken($token);
    }
}
