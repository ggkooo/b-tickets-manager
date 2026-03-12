<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'login', 'active', 'is_admin', 'created_at', 'updated_at']);

        return response()->json($users);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $payload = [
            'name' => $validated['name'] ?? $user->name,
            'login' => $validated['login'] ?? $user->login,
            'active' => $validated['active'] ?? $user->active,
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->fresh()->only(['id', 'uuid', 'name', 'login', 'active', 'is_admin', 'created_at', 'updated_at']),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->is_admin && User::where('is_admin', true)->count() === 1) {
            return response()->json([
                'message' => 'Cannot delete the last administrator',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function makeAdmin(User $user): JsonResponse
    {
        if ($user->is_admin) {
            return response()->json([
                'message' => 'User is already an administrator',
                'user' => $user->only(['id', 'uuid', 'name', 'login', 'active', 'is_admin', 'created_at', 'updated_at']),
            ]);
        }

        $user->update([
            'is_admin' => true,
        ]);

        return response()->json([
            'message' => 'User promoted to administrator successfully',
            'user' => $user->fresh()->only(['id', 'uuid', 'name', 'login', 'active', 'is_admin', 'created_at', 'updated_at']),
        ]);
    }

    public function removeAdmin(User $user): JsonResponse
    {
        if (!$user->is_admin) {
            return response()->json([
                'message' => 'User is not an administrator',
                'user' => $user->only(['id', 'uuid', 'name', 'login', 'active', 'is_admin', 'created_at', 'updated_at']),
            ]);
        }

        if (User::where('is_admin', true)->count() === 1) {
            return response()->json([
                'message' => 'Cannot remove administrator access from the last administrator',
            ], 422);
        }

        $user->update([
            'is_admin' => false,
        ]);

        return response()->json([
            'message' => 'Administrator access removed successfully',
            'user' => $user->fresh()->only(['id', 'uuid', 'name', 'login', 'active', 'is_admin', 'created_at', 'updated_at']),
        ]);
    }
}