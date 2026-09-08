<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('attended_by_user_id')
                ->nullable()
                ->after('guiche')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('ticket_archives', function (Blueprint $table) {
            $table->foreignId('attended_by_user_id')
                ->nullable()
                ->after('guiche')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['attended_by_user_id']);
            $table->dropColumn('attended_by_user_id');
        });

        Schema::table('ticket_archives', function (Blueprint $table) {
            $table->dropForeign(['attended_by_user_id']);
            $table->dropColumn('attended_by_user_id');
        });
    }
};
