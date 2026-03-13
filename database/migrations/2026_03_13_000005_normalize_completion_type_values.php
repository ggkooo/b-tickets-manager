<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('tickets')
            ->where('completed', true)
            ->where(function ($query) {
                $query->whereNull('completion_type')
                    ->orWhereNotIn('completion_type', ['completed', 'canceled']);
            })
            ->update(['completion_type' => 'completed']);

        DB::table('ticket_archives')
            ->where(function ($query) {
                $query->whereNull('completion_type')
                    ->orWhereNotIn('completion_type', ['completed', 'canceled']);
            })
            ->update(['completion_type' => 'completed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No safe rollback for normalized historical values.
    }
};
