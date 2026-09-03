<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
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
            ->get();

        return response()->json(UserResource::collection($users)->resolve());
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('manage', $user);

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
            'user' => (new UserResource($user->fresh()))->resolve(),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('manage', $user);

        if ($user->is_super_admin && User::isOnlySuperAdminAt($request->user()->location)) {
            return response()->json([
                'message' => 'Cannot delete the last super administrator',
            ], 422);
        }

        if ($user->is_admin && User::isOnlyAdminAt($request->user()->location)) {
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
        $this->authorize('manage', $user);

        if ($user->is_admin) {
            return response()->json([
                'message' => 'User is already an administrator',
                'user' => (new UserResource($user))->resolve(),
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
            'user' => (new UserResource($user->fresh()))->resolve(),
        ]);
    }

    public function removeAdmin(Request $request, User $user): JsonResponse
    {
        $this->authorize('manage', $user);

        if (!$user->is_admin) {
            return response()->json([
                'message' => 'User is not an administrator',
                'user' => (new UserResource($user))->resolve(),
            ]);
        }

        if ($user->is_super_admin) {
            return response()->json([
                'message' => 'Super administrator role cannot be changed through this route.',
            ], 422);
        }

        if (User::isOnlyAdminAt($request->user()->location)) {
            return response()->json([
                'message' => 'Cannot remove administrator access from the last administrator',
            ], 422);
        }

        $user->update([
            'is_admin' => false,
        ]);

        return response()->json([
            'message' => 'Administrator access removed successfully',
            'user' => (new UserResource($user->fresh()))->resolve(),
        ]);
    }
}
