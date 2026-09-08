<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printer_settings', function (Blueprint $table) {
            $table->id();
            $table->string('location')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('connection_type')->default('network');
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('share_path')->nullable();
            $table->string('profile')->default('simple');
            $table->string('header')->default('SENHA DE ATENDIMENTO');
            $table->timestamps();

            $table->index('enabled');
            $table->index('connection_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_settings');
    }
};
