<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public-facing shape of a User: everything except the password/token
 * fields. Used everywhere a user is returned from the admin/auth endpoints
 * so that field list only needs to be maintained in one place.
 *
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'login' => $this->login,
            'location' => $this->location,
            'active' => $this->active,
            'is_admin' => $this->is_admin,
            'is_super_admin' => $this->is_super_admin,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
