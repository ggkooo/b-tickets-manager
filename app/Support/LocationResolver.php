<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class LocationResolver
{
    public static function resolveFromRequest(Request $request): string
    {
        $user = $request->user('sanctum') ?? $request->user();

        if ($user) {
            return $user->location;
        }

        $rawLocation = $request->input('location', $request->header('X-UNILAB-LOCATION'));
        $location = strtolower(trim((string) $rawLocation));

        if (!in_array($location, User::allowedLocations(), true)) {
            abort(response()->json([
                'message' => 'Local invalido. Locais permitidos: ' . implode(', ', User::allowedLocations()) . '.',
            ], 422));
        }

        return $location;
    }
}
