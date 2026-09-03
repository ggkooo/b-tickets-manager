<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->updateOrInsert([
            'login' => 'admin',
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => 'Administrador',
            'password' => Hash::make('admin'),
            'active' => true,
            'is_admin' => true,
            'remember_token' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('login', 'admin')->delete();
    }
};