<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use App\Models\Ticket;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tickets:archive-completed', function () {
    $today = Carbon::today();
    $archived = 0;

    Ticket::where('completed', true)
        ->where(function ($query) use ($today) {
            $query->where('completed_at', '<', $today)
                ->orWhere(function ($fallback) use ($today) {
                    $fallback->whereNull('completed_at')
                        ->where('updated_at', '<', $today);
                });
        })
        ->orderBy('id')
        ->chunkById(500, function ($tickets) use (&$archived) {
            $now = Carbon::now();
            $rows = [];
            $ids = [];

            foreach ($tickets as $ticket) {
                $completionType = $ticket->completion_type === 'canceled' ? 'canceled' : 'completed';

                $rows[] = [
                    'ticket_id' => $ticket->id,
                    'key' => $ticket->key,
                    'service_type' => $ticket->service_type,
                    'guiche' => $ticket->guiche,
                    'called_at' => $ticket->called_at,
                    'completed_at' => $ticket->completed_at ?? $ticket->updated_at,
                    'completion_type' => $completionType,
                    'ticket_created_at' => $ticket->created_at,
                    'ticket_updated_at' => $ticket->updated_at,
                    'archived_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $ids[] = $ticket->id;
            }

            DB::transaction(function () use ($rows, $ids, &$archived) {
                DB::table('ticket_archives')->insert($rows);
                Ticket::whereIn('id', $ids)->delete();
                $archived += count($ids);
            });
        });

    $this->info("{$archived} senha(s) finalizada(s) arquivada(s).");
})->purpose('Archive completed tickets from previous days');

Schedule::command('tickets:archive-completed')
    ->dailyAt('00:05')
    ->withoutOverlapping();
