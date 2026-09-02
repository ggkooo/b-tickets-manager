<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * @param  \App\Http\Requests\RegisterRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();
        $location = $request->user()?->location;

        if ($request->user() === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($location === null) {
            return response()->json([
                'message' => 'Usuario autenticado sem local configurado.',
            ], 422);
        }

        $user = User::create([
            'uuid' => Str::uuid(),
            'name' => $validated['name'],
            'login' => $validated['login'],
            'location' => $location,
            'password' => Hash::make($validated['password']),
            'is_admin' => false,
            'is_super_admin' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully',
            'data' => [
                'user' => (new UserResource($user))->resolve(),
            ]
        ], 201);
    }

    /**
     * Authenticate a user and return a token.
     *
     * @param  \App\Http\Requests\LoginRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        $attemptCredentials = [
            'login' => $credentials['login'],
            'password' => $credentials['password'],
            'location' => $credentials['location'],
        ];

        if (!Auth::attempt($attemptCredentials)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = User::where('login', $credentials['login'])
            ->where('location', $credentials['location'])
            ->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => (new UserResource($user))->resolve(),
            ]
        ]);
    }
}
