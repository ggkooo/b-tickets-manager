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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('location');
            // 'upload': an mp4 file stored on this server (filename set, url null).
            // 'link': an external URL — YouTube or a direct video link (url set, filename null).
            $table->string('type');
            $table->string('filename')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();

            $table->index('location');
            $table->index(['location', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
