<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function attendances(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        $archivedAttendances = DB::table('ticket_archives')
            ->selectRaw('service_type, called_at, completion_type, ticket_created_at as created_reference, COALESCE(completed_at, ticket_updated_at) as completed_reference')
            ->whereBetween(DB::raw('COALESCE(completed_at, ticket_updated_at)'), [$startDate, $endDate])
            ->get();

        $activeAttendances = DB::table('tickets')
            ->selectRaw('service_type, called_at, completion_type, created_at as created_reference, COALESCE(completed_at, updated_at) as completed_reference')
            ->where('completed', true)
            ->whereBetween(DB::raw('COALESCE(completed_at, updated_at)'), [$startDate, $endDate])
            ->get();

        $attendances = $archivedAttendances->concat($activeAttendances);

        $totalAttendances = $attendances->count();
        $priorityAttendances = $attendances->where('service_type', 'Atendimento Preferencial')->count();
        $otherAttendances = $totalAttendances - $priorityAttendances;
        $canceledAttendances = $attendances->where('completion_type', 'canceled')->count();
        $completedAttendances = $attendances->where('completion_type', 'completed')->count();
        $unknownOutcomeAttendances = $totalAttendances - $canceledAttendances - $completedAttendances;

        $daysInRange = $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()) + 1;
        $averageAttendancesPerDay = $daysInRange > 0
            ? round($totalAttendances / $daysInRange, 2)
            : 0;

        $averageWaitSeconds = $this->calculateAverageWaitSeconds($attendances);

        return response()->json([
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'days' => $daysInRange,
            ],
            'average_wait_time' => [
                'seconds' => $averageWaitSeconds,
                'formatted' => $this->formatSeconds($averageWaitSeconds),
            ],
            'average_attendances_per_day' => $averageAttendancesPerDay,
            'attendances_per_day' => $this->buildAttendancesPerDay($attendances),
            'attendances_by_type' => [
                'priority' => $priorityAttendances,
                'others' => $otherAttendances,
            ],
            'attendances_by_outcome' => [
                'completed' => $completedAttendances,
                'canceled' => $canceledAttendances,
                'unknown' => $unknownOutcomeAttendances,
            ],
            'total_attendances' => $totalAttendances,
        ]);
    }

    private function calculateAverageWaitSeconds(Collection $attendances): int
    {
        $waitSeconds = $attendances
            ->filter(fn ($item) => !empty($item->called_at) && !empty($item->created_reference))
            ->map(function ($item) {
                $createdAt = Carbon::parse($item->created_reference);
                $calledAt = Carbon::parse($item->called_at);

                return max(0, $createdAt->diffInSeconds($calledAt, false));
            });

        if ($waitSeconds->isEmpty()) {
            return 0;
        }

        return (int) round($waitSeconds->avg());
    }

    private function buildAttendancesPerDay(Collection $attendances): array
    {
        return $attendances
            ->groupBy(function ($item) {
                return Carbon::parse($item->completed_reference)->toDateString();
            })
            ->map(fn (Collection $items) => $items->count())
            ->sortKeys()
            ->toArray();
    }

    private function formatSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }
}
