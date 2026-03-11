<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('key');
            $table->string('service_type');
            $table->string('guiche')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('ticket_created_at')->nullable();
            $table->timestamp('ticket_updated_at')->nullable();
            $table->timestamp('archived_at');
            $table->timestamps();

            $table->index(['completed_at']);
            $table->index(['service_type']);
            $table->index(['archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_archives');
    }
};
