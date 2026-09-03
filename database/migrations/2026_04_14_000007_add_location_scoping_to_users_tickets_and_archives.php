<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('location')->default(User::LOCATION_CAMPUS)->after('login');
        });

        DB::table('users')
            ->whereNull('location')
            ->orWhere('location', '')
            ->update(['location' => User::LOCATION_CAMPUS]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_login_unique');
            $table->unique(['login', 'location'], 'users_login_location_unique');
            $table->index('location');
        });

        DB::table('users')->updateOrInsert([
            'login' => 'admin',
            'location' => User::LOCATION_CENTRO,
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

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('location')->default(User::LOCATION_CAMPUS)->after('key');
            $table->index('location');
            $table->index(['location', 'key']);
        });

        Schema::table('ticket_archives', function (Blueprint $table) {
            $table->string('location')->default(User::LOCATION_CAMPUS)->after('key');
            $table->index('location');
            $table->index(['location', 'key']);
        });

        DB::table('tickets')
            ->whereNull('location')
            ->orWhere('location', '')
            ->update(['location' => User::LOCATION_CAMPUS]);

        DB::table('ticket_archives')
            ->whereNull('location')
            ->orWhere('location', '')
            ->update(['location' => User::LOCATION_CAMPUS]);
    }

    public function down(): void
    {
        Schema::table('ticket_archives', function (Blueprint $table) {
            $table->dropIndex('ticket_archives_location_key_index');
            $table->dropIndex('ticket_archives_location_index');
            $table->dropColumn('location');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_location_key_index');
            $table->dropIndex('tickets_location_index');
            $table->dropColumn('location');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_login_location_unique');
            $table->dropIndex('users_location_index');
            $table->unique('login');
            $table->dropColumn('location');
        });
    }
};
