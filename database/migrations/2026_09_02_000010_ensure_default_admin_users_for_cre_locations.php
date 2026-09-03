<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $locations = [
        User::LOCATION_CRE_IJUI,
        User::LOCATION_CRE_SANTA_ROSA,
        User::LOCATION_CRE_PANAMBI,
        User::LOCATION_CRE_TRES_PASSOS,
    ];

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

    public function down(): void
    {
        DB::table('users')
            ->where('login', 'admin')
            ->whereIn('location', $this->locations)
            ->delete();
    }
};
