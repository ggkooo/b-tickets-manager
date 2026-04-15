<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const LOCATION_CAMPUS = 'campus';
    public const LOCATION_CENTRO = 'centro';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'login',
        'location',
        'password',
        'active',
        'is_admin',
        'is_super_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'is_admin' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    public function hasAdminAccess(): bool
    {
        return $this->is_admin || $this->is_super_admin;
    }

    public function hasSuperAdminAccess(): bool
    {
        return $this->is_super_admin;
    }

    /**
     * @return array<int, string>
     */
    public static function allowedLocations(): array
    {
        return [
            self::LOCATION_CAMPUS,
            self::LOCATION_CENTRO,
        ];
    }
}
