<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (User::allowedLocations() as $location) {
            User::query()->updateOrCreate([
                'login' => 'admin',
                'location' => $location,
            ], [
                'uuid' => (string) Str::uuid(),
                'name' => 'Administrador',
                'password' => Hash::make('admin'),
                'active' => true,
                'is_admin' => true,
                'is_super_admin' => true,
            ]);
        }
    }
}
