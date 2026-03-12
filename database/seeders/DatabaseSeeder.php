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

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'login' => 'admin',
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => 'Administrador',
            'password' => Hash::make('admin'),
            'active' => true,
            'is_admin' => true,
        ]);
    }
}
