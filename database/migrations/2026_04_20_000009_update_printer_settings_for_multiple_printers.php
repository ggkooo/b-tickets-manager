<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printer_settings', function (Blueprint $table) {
            $table->string('name')->nullable()->after('location');
        });

        DB::table('printer_settings')
            ->whereNull('name')
            ->update(['name' => 'Impressora principal']);

        Schema::table('printer_settings', function (Blueprint $table) {
            $table->dropUnique(['location']);
            $table->unique(['location', 'name']);
            $table->index(['location', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::table('printer_settings', function (Blueprint $table) {
            $table->dropIndex(['location', 'enabled']);
            $table->dropUnique(['location', 'name']);
            $table->dropColumn('name');
            $table->unique('location');
        });
    }
};