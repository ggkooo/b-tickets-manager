<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceReportRequest;
use App\Services\AttendanceReportBuilder;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function attendances(AttendanceReportRequest $request, AttendanceReportBuilder $reportBuilder)
    {
        $location = $request->user()->location;
        $startDate = Carbon::parse($request->validated('start_date'))->startOfDay();
        $endDate = Carbon::parse($request->validated('end_date'))->endOfDay();

        return response()->json($reportBuilder->build($location, $startDate, $endDate));
    }
}
