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
            ->leftJoin('users', 'users.id', '=', 'ticket_archives.attended_by_user_id')
            ->selectRaw("ticket_archives.service_type, ticket_archives.guiche, ticket_archives.attended_by_user_id, users.name as attended_by_user_name, users.login as attended_by_user_login, ticket_archives.called_at, CASE WHEN ticket_archives.completion_type = 'canceled' THEN 'canceled' ELSE 'completed' END as completion_type, ticket_archives.ticket_created_at as created_reference, COALESCE(ticket_archives.completed_at, ticket_archives.ticket_updated_at) as completed_reference")
            ->whereBetween(DB::raw('COALESCE(ticket_archives.completed_at, ticket_archives.ticket_updated_at)'), [$startDate, $endDate])
            ->get();

        $activeAttendances = DB::table('tickets')
            ->leftJoin('users', 'users.id', '=', 'tickets.attended_by_user_id')
            ->selectRaw("tickets.service_type, tickets.guiche, tickets.attended_by_user_id, users.name as attended_by_user_name, users.login as attended_by_user_login, tickets.called_at, CASE WHEN tickets.completion_type = 'canceled' THEN 'canceled' ELSE 'completed' END as completion_type, tickets.created_at as created_reference, COALESCE(tickets.completed_at, tickets.updated_at) as completed_reference")
            ->where('tickets.completed', true)
            ->whereBetween(DB::raw('COALESCE(tickets.completed_at, tickets.updated_at)'), [$startDate, $endDate])
            ->get();

        $users = DB::table('users')
            ->select('id', 'name', 'login')
            ->orderBy('name')
            ->get();

        $attendances = $this->enrichAttendancesWithUsers($archivedAttendances->concat($activeAttendances), $users);
        $attendancesByGuiche = $this->buildAttendancesByGuiche($attendances);
        $attendancesByUser = $this->buildAttendancesByUser($users, $attendancesByGuiche);

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
            'attendances_by_guiche' => $attendancesByGuiche,
            'attendances_by_user' => $attendancesByUser,
            'total_attendances' => $totalAttendances,
        ]);
    }

    private function buildAttendancesByGuiche(Collection $attendances): array
    {
        return $attendances
            ->groupBy(function ($item) {
                if (!empty($item->resolved_attended_by_user_id)) {
                    return 'user:' . $item->resolved_attended_by_user_id;
                }

                return 'guiche:' . ($item->guiche_label ?: 'Sem identificacao');
            })
            ->map(function (Collection $items) {
                $first = $items->first();
                $total = $items->count();
                $completed = $items->where('completion_type', 'completed')->count();
                $canceled = $items->where('completion_type', 'canceled')->count();

                return [
                    'guiche' => $first->guiche_label ?: 'Sem identificacao',
                    'attended_by_user_id' => $first->resolved_attended_by_user_id,
                    'attended_by_user_name' => $first->resolved_attended_by_user_name,
                    'attended_by_user_login' => $first->resolved_attended_by_user_login,
                    'total' => $total,
                    'completed' => $completed,
                    'canceled' => $canceled,
                    'unknown' => $total - $completed - $canceled,
                ];
            })
            ->sortBy([
                ['guiche', 'asc'],
                ['attended_by_user_name', 'asc'],
            ])
            ->values()
            ->toArray();
    }

    private function buildAttendancesByUser(Collection $users, array $attendancesByGuiche): array
    {
        $statsByUserId = collect($attendancesByGuiche)
            ->filter(fn (array $row) => !empty($row['attended_by_user_id']))
            ->keyBy('attended_by_user_id');

        return $users
            ->map(function ($user) use ($statsByUserId) {
                $stats = $statsByUserId->get($user->id, [
                    'total' => 0,
                    'completed' => 0,
                    'canceled' => 0,
                    'unknown' => 0,
                ]);

                return [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'login' => $user->login,
                    'guiche' => $this->formatGuicheLabel($user->name),
                    'total' => $stats['total'],
                    'completed' => $stats['completed'],
                    'canceled' => $stats['canceled'],
                    'unknown' => $stats['unknown'],
                ];
            })
            ->values()
            ->toArray();
    }

    private function enrichAttendancesWithUsers(Collection $attendances, Collection $users): Collection
    {
        $usersByLogin = $users->keyBy(fn ($user) => strtolower(trim((string) $user->login)));
        $usersByName = $users->keyBy(fn ($user) => strtolower(trim((string) $user->name)));

        return $attendances->map(function ($item) use ($usersByLogin, $usersByName) {
            $resolvedUserId = $item->attended_by_user_id;
            $resolvedUserName = $item->attended_by_user_name;
            $resolvedUserLogin = $item->attended_by_user_login;

            if (empty($resolvedUserId) && !empty($item->guiche)) {
                $guicheKey = strtolower(trim((string) $item->guiche));
                $matchedUser = $usersByLogin->get($guicheKey) ?? $usersByName->get($guicheKey);

                if ($matchedUser) {
                    $resolvedUserId = $matchedUser->id;
                    $resolvedUserName = $matchedUser->name;
                    $resolvedUserLogin = $matchedUser->login;
                }
            }

            $guicheLabel = $this->formatGuicheLabel($resolvedUserName ?: $item->guiche);

            return (object) array_merge((array) $item, [
                'resolved_attended_by_user_id' => $resolvedUserId,
                'resolved_attended_by_user_name' => $resolvedUserName,
                'resolved_attended_by_user_login' => $resolvedUserLogin,
                'guiche_label' => $guicheLabel,
            ]);
        });
    }

    private function formatGuicheLabel(?string $value): string
    {
        if ($value === null) {
            return 'Sem identificacao';
        }

        $label = trim($value);

        if ($label === '') {
            return 'Sem identificacao';
        }

        $label = str_replace(['_', '-'], ' ', $label);
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;
        $label = preg_replace('/\bguiche\b/i', 'Guiche', $label) ?? $label;

        return ucwords(strtolower($label));
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
