<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const LOCATION_CAMPUS = 'campus';
    public const LOCATION_CENTRO = 'centro';

    public const LOCATION_CRE_IJUI = 'ijui';
    public const LOCATION_CRE_SANTA_ROSA = 'santa-rosa';
    public const LOCATION_CRE_PANAMBI = 'panambi';
    public const LOCATION_CRE_TRES_PASSOS = 'tres-passos';

    public const INSTITUTION_UNILAB = 'unilab';
    public const INSTITUTION_CRE = 'cre';

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

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
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

    public static function allowedLocations(): array
    {
        return array_keys(self::locationInstitutionMap());
    }

    public static function locationInstitutionMap(): array
    {
        return [
            self::LOCATION_CAMPUS => self::INSTITUTION_UNILAB,
            self::LOCATION_CENTRO => self::INSTITUTION_UNILAB,
            self::LOCATION_CRE_IJUI => self::INSTITUTION_CRE,
            self::LOCATION_CRE_SANTA_ROSA => self::INSTITUTION_CRE,
            self::LOCATION_CRE_PANAMBI => self::INSTITUTION_CRE,
            self::LOCATION_CRE_TRES_PASSOS => self::INSTITUTION_CRE,
        ];
    }

    public static function allowedLocationsForInstitution(string $institution): array
    {
        return array_keys(array_filter(
            self::locationInstitutionMap(),
            fn (string $mappedInstitution) => $mappedInstitution === $institution,
        ));
    }

    public static function institutionForLocation(?string $location): ?string
    {
        return self::locationInstitutionMap()[$location] ?? null;
    }
}
