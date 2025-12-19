<?php

namespace App\Services\Auth;

use App\Facades\Audit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class AuthService
{
    public function register(
        string $name,
        string $email,
        string $password
    ): User {

        try {
            return DB::transaction(
                function () use ($name, $email, $password) {
                    $user = User::create([
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make($password)
                    ]);

                    Audit::info('User registered', [
                        'user_id' => $user->id,
                    ]);

                    return $user;
                }
            );
        } catch (Throwable $e) {
            Audit::error('User registration failed');
            throw $e;
        }
    }


    public function login(string $email, string $password): string
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            Audit::warning('Failed login attempt');
            throw ValidationException::withMessages([
                'email' => ['invalid credentials']
            ]);
        }

        Audit::info('User logged in successfully');
        return $user->createToken('api')->plainTextToken;
    }
}
