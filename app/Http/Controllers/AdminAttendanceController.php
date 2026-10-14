<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = Carbon::parse($request->input('date', today()->toDateString()));

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        $users = User::where('admin_flag', false)
            ->orderBy('id')
            ->get();

        $attendanceRecords = Attendance::with('attendanceBreaks')
            ->whereDate('work_date', $date->toDateString())
            ->get();

        foreach ($attendanceRecords as $attendance) {
            $totalBreakSeconds = 0;

            foreach ($attendance->attendanceBreaks as $break) {
                if ($break->break_in && $break->break_out) {
                    $breakIn = Carbon::parse($break->break_in);
                    $breakOut = Carbon::parse($break->break_out);

                    $totalBreakSeconds += $breakOut->diffInSeconds($breakIn);
                }
            }

            $attendance->total_break_time = gmdate('H:i:s', $totalBreakSeconds);

            if ($attendance->clock_in && $attendance->clock_out) {
                $clockIn = Carbon::parse($attendance->clock_in);
                $clockOut = Carbon::parse($attendance->clock_out);

                $totalWorkSeconds = max(
                    0,
                    $clockOut->diffInSeconds($clockIn) - $totalBreakSeconds
                );

                $attendance->total_time = gmdate('H:i:s', $totalWorkSeconds);
            } else {
                $attendance->total_time = null;
            }
        }

        return view('admin.admin-attendance-list', compact(
            'date',
            'previousDay',
            'nextDay',
            'users',
            'attendanceRecords'
        ));
    }
}
