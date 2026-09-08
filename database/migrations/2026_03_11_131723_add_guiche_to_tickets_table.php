<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('guiche')->nullable()->after('completed');
            $table->timestamp('called_at')->nullable()->after('guiche');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
        });
    }
};
