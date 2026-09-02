<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolves which location (totem) a request belongs to: the authenticated
 * user's own location, or — for the public, unauthenticated ticket routes —
 * the `location` input / `X-UNILAB-LOCATION` header, validated against the
 * known list of locations.
 */
class LocationResolver
{
    public static function resolveFromRequest(Request $request): string
    {
        if ($request->user()) {
            return $request->user()->location;
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
