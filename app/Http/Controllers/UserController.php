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
        $location = $request->user()->location;

        $users = User::query()
            ->where('location', $location)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'login', 'location', 'active', 'is_admin', 'is_super_admin', 'created_at', 'updated_at']);

        return response()->json($users);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->abortIfDifferentLocation($request, $user);

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
            'user' => $user->fresh()->only(['id', 'uuid', 'name', 'login', 'location', 'active', 'is_admin', 'is_super_admin', 'created_at', 'updated_at']),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->abortIfDifferentLocation($request, $user);

        if ($user->is_super_admin && User::where('is_super_admin', true)->where('location', $request->user()->location)->count() === 1) {
            return response()->json([
                'message' => 'Cannot delete the last super administrator',
            ], 422);
        }

        if ($user->is_admin && User::where('is_admin', true)->where('location', $request->user()->location)->count() === 1) {
            return response()->json([
                'message' => 'Cannot delete the last administrator',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function makeAdmin(Request $request, User $user): JsonResponse
    {
        $this->abortIfDifferentLocation($request, $user);

        if ($user->is_admin) {
            return response()->json([
                'message' => 'User is already an administrator',
                'user' => $user->only(['id', 'uuid', 'name', 'login', 'location', 'active', 'is_admin', 'is_super_admin', 'created_at', 'updated_at']),
            ]);
        }

        if ($user->is_super_admin) {
            return response()->json([
                'message' => 'Super administrator role cannot be changed through this route.',
            ], 422);
        }

        $user->update([
            'is_admin' => true,
        ]);

        return response()->json([
            'message' => 'User promoted to administrator successfully',
            'user' => $user->fresh()->only(['id', 'uuid', 'name', 'login', 'location', 'active', 'is_admin', 'is_super_admin', 'created_at', 'updated_at']),
        ]);
    }

    public function removeAdmin(Request $request, User $user): JsonResponse
    {
        $this->abortIfDifferentLocation($request, $user);

        if (!$user->is_admin) {
            return response()->json([
                'message' => 'User is not an administrator',
                'user' => $user->only(['id', 'uuid', 'name', 'login', 'location', 'active', 'is_admin', 'is_super_admin', 'created_at', 'updated_at']),
            ]);
        }

        if ($user->is_super_admin) {
            return response()->json([
                'message' => 'Super administrator role cannot be changed through this route.',
            ], 422);
        }

        if (User::where('is_admin', true)->where('location', $request->user()->location)->count() === 1) {
            return response()->json([
                'message' => 'Cannot remove administrator access from the last administrator',
            ], 422);
        }

        $user->update([
            'is_admin' => false,
        ]);

        return response()->json([
            'message' => 'Administrator access removed successfully',
            'user' => $user->fresh()->only(['id', 'uuid', 'name', 'login', 'location', 'active', 'is_admin', 'is_super_admin', 'created_at', 'updated_at']),
        ]);
    }

    private function abortIfDifferentLocation(Request $request, User $user): void
    {
        if ($user->location !== $request->user()->location) {
            abort(404);
        }
    }
}