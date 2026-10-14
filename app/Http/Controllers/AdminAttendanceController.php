<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAdminAttendanceRequest;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : Carbon::today();

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        $users = User::where('admin_flag', false)
            ->orderBy('id')
            ->get();

        $attendanceRecords = Attendance::with('attendanceBreaks')
            ->whereDate('work_date', $date->format('Y-m-d'))
            ->whereIn('user_id', $users->pluck('id'))
            ->get();

        foreach ($attendanceRecords as $attendance) {
            $totalBreakSeconds = 0;

            foreach ($attendance->attendanceBreaks as $break) {
                if ($break->break_in && $break->break_out) {
                    $totalBreakSeconds += Carbon::parse($break->break_in)
                        ->diffInSeconds(Carbon::parse($break->break_out));
                }
            }

            $totalWorkSeconds = 0;

            if ($attendance->clock_in && $attendance->clock_out) {
                $totalWorkSeconds = Carbon::parse($attendance->clock_in)
                    ->diffInSeconds(Carbon::parse($attendance->clock_out));

                $totalWorkSeconds = max(
                    0,
                    $totalWorkSeconds - $totalBreakSeconds
                );
            }

            $attendance->total_break_time = $this->formatDuration(
                $totalBreakSeconds
            );

            $attendance->total_time = $attendance->clock_out
                ? $this->formatDuration($totalWorkSeconds)
                : null;
        }

        return view('admin.admin-attendance-list', compact(
            'date',
            'previousDay',
            'nextDay',
            'users',
            'attendanceRecords'
        ));
    }

    public function show($id)
    {
        $attendance = Attendance::with([
            'user',
            'attendanceBreaks',
        ])->findOrFail($id);

        $user = $attendance->user;

        abort_if(!$user || $user->admin_flag, 404);

        $clockIn = $attendance->clock_in
            ? Carbon::parse($attendance->clock_in)->format('H:i')
            : '';

        $clockOut = $attendance->clock_out
            ? Carbon::parse($attendance->clock_out)->format('H:i')
            : '';

        $breaks = $attendance->attendanceBreaks
            ->map(function ($break) {
                return [
                    'id' => $break->id,
                    'break_in' => $break->break_in
                        ? Carbon::parse($break->break_in)->format('H:i')
                        : '',
                    'break_out' => $break->break_out
                        ? Carbon::parse($break->break_out)->format('H:i')
                        : '',
                ];
            })
            ->values()
            ->all();

        $attendanceRecord = [
            'id' => $attendance->id,
            'year' => $attendance->work_date->format('Y年'),
            'date' => $attendance->work_date->format('n月j日'),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'breaks' => $breaks,
            'comment' => '',
        ];

        return view('admin.admin-detail', compact(
            'attendanceRecord',
            'user'
        ));
    }

    public function update(
        UpdateAdminAttendanceRequest $request,
        $id
    ) {
        $attendance = Attendance::with([
            'user',
            'attendanceBreaks',
        ])->findOrFail($id);

        abort_if(!$attendance->user || $attendance->user->admin_flag, 404);

        $validated = $request->validated();

        DB::transaction(function () use ($attendance, $validated) {
            $attendance = Attendance::whereKey($attendance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $attendance->update([
                'clock_in' => $validated['new_clock_in'],
                'clock_out' => $validated['new_clock_out'],
            ]);

            $breakIns = $validated['new_break_in'] ?? [];
            $breakOuts = $validated['new_break_out'] ?? [];

            $existingBreaks = $attendance->attendanceBreaks()
                ->orderBy('id')
                ->get();

            $usedBreakIds = [];

            foreach (array_unique(array_merge(
                array_keys($breakIns),
                array_keys($breakOuts)
            )) as $index) {
                $breakIn = $breakIns[$index] ?? null;
                $breakOut = $breakOuts[$index] ?? null;

                if (blank($breakIn) && blank($breakOut)) {
                    continue;
                }

                $break = $existingBreaks->get((int) $index);

                if ($break) {
                    $break->update([
                        'break_in' => $breakIn,
                        'break_out' => $breakOut,
                    ]);

                    $usedBreakIds[] = $break->id;
                } else {
                    $newBreak = $attendance->attendanceBreaks()->create([
                        'break_in' => $breakIn,
                        'break_out' => $breakOut,
                    ]);

                    $usedBreakIds[] = $newBreak->id;
                }
            }

            $attendance->attendanceBreaks()
                ->whereNotIn('id', $usedBreakIds)
                ->delete();
        });

        return redirect(
            '/admin/attendance/detail/' . $id
        )->with('success', '勤怠情報を修正しました');
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%d:%02d', $hours, $minutes);
    }
}
