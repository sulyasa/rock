<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user with CAPTCHA verification.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $captchaKey = $request->input('captcha_key');
        $captchaValue = $request->input('captcha_value');

        // Retrieve expected captcha answer from cache
        $expectedValue = Cache::get("captcha_{$captchaKey}");

        if (!$expectedValue || strcasecmp((string)$expectedValue, $captchaValue) !== 0) {
            throw ValidationException::withMessages([
                'captcha_value' => ['Неверный защитный код (CAPTCHA).'],
            ]);
        }

        // Clear captcha from cache after use
        Cache::forget("captcha_{$captchaKey}");

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => 'player', // default role
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Пользователь успешно зарегистрирован.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }

    /**
     * Authenticate user and issue API token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный адрес электронной почты или пароль.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Revoke current user's token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Сессия успешно завершена.',
        ]);
    }

    /**
     * Generate captcha for registration form.
     */
    public function generateCaptcha(): JsonResponse
    {
        $captchaKey = bin2hex(random_bytes(16));
        // Simple 4-digit code for math captcha
        $num1 = random_int(1, 9);
        $num2 = random_int(1, 9);
        $answer = $num1 + $num2;

        Cache::put("captcha_{$captchaKey}", (string)$answer, now()->addMinutes(10));

        return response()->json([
            'captcha_key' => $captchaKey,
            'question' => "Решите пример: {$num1} + {$num2} = ?",
        ]);
    }
}
