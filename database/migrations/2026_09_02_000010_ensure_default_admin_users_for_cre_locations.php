<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * List of CRE locations that need a bootstrap super-admin account,
     * mirroring how "centro" was bootstrapped for Unilab in the
     * 2026_04_14_000007 migration.
     */
    private array $locations = [
        User::LOCATION_CRE_IJUI,
        User::LOCATION_CRE_SANTA_ROSA,
        User::LOCATION_CRE_PANAMBI,
        User::LOCATION_CRE_TRES_PASSOS,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->locations as $location) {
            DB::table('users')->updateOrInsert([
                'login' => 'admin',
                'location' => $location,
            ], [
                'uuid' => (string) Str::uuid(),
                'name' => 'Administrador',
                'password' => Hash::make('admin'),
                'active' => true,
                'is_admin' => true,
                'is_super_admin' => true,
                'remember_token' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('login', 'admin')
            ->whereIn('location', $this->locations)
            ->delete();
    }
};
