<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminStaffAttendanceController extends Controller
{
    public function index(Request $request, $id)
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m'],
        ]);

        $user = User::where('admin_status', false)->findOrFail($id);

        $date = $request->filled('date')
            ? Carbon::createFromFormat('!Y-m', $request->input('date'))
            : now()->startOfMonth();

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $attendanceRecords = Attendance::with('attendanceBreaks')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [
                $startOfMonth->toDateString(),
                $endOfMonth->toDateString(),
            ])
            ->get()
            ->keyBy(
                fn($attendance) => $attendance->work_date->format('Y-m-d')
            );

        $formattedAttendanceRecords = collect();

        for (
            $day = $startOfMonth->copy();
            $day->lte($endOfMonth);
            $day->addDay()
        ) {
            $attendance = $attendanceRecords->get($day->format('Y-m-d'));

            $totalBreakSeconds = 0;
            $totalWorkSeconds = null;

            if ($attendance) {
                foreach ($attendance->attendanceBreaks as $break) {
                    if ($break->break_in && $break->break_out) {
                        $breakIn = Carbon::parse($break->break_in);
                        $breakOut = Carbon::parse($break->break_out);

                        $totalBreakSeconds += $breakOut->diffInSeconds($breakIn);
                    }
                }

                if ($attendance->clock_in && $attendance->clock_out) {
                    $clockIn = Carbon::parse($attendance->clock_in);
                    $clockOut = Carbon::parse($attendance->clock_out);

                    $totalWorkSeconds = max(
                        0,
                        $clockOut->diffInSeconds($clockIn)
                        - $totalBreakSeconds
                    );
                }
            }

            $formattedAttendanceRecords->push([
                'id' => $attendance?->id,
                'date' => $day->format('m/d')
                    . '('
                    . ['日', '月', '火', '水', '木', '金', '土'][$day->dayOfWeek]
                    . ')',
                'clock_in' => $attendance?->clock_in
                    ? substr((string) $attendance->clock_in, 0, 5)
                    : '',
                'clock_out' => $attendance?->clock_out
                    ? substr((string) $attendance->clock_out, 0, 5)
                    : '',
                'total_break_time' => $totalBreakSeconds > 0
                    ? gmdate('H:i:s', $totalBreakSeconds)
                    : null,
                'total_time' => $totalWorkSeconds !== null
                    ? gmdate('H:i:s', $totalWorkSeconds)
                    : null,
            ]);
        }

        return view(
            'admin.staff-attendance-list',
            compact(
                'user',
                'date',
                'previousMonth',
                'nextMonth',
                'formattedAttendanceRecords'
            )
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year_month' => ['required', 'date_format:Y-m'],
        ]);

        $user = User::where('admin_status', false)
            ->findOrFail($validated['user_id']);

        $date = Carbon::createFromFormat(
            '!Y-m',
            $validated['year_month']
        );

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $attendanceRecords = Attendance::with('attendanceBreaks')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [
                $startOfMonth->toDateString(),
                $endOfMonth->toDateString(),
            ])
            ->get()
            ->keyBy(
                fn($attendance) => $attendance->work_date->format('Y-m-d')
            );

        $fileName = sprintf(
            'staff_attendance_%d_%s.csv',
            $user->id,
            $date->format('Y-m')
        );

        return response()->streamDownload(
            function () use ($startOfMonth, $endOfMonth, $attendanceRecords) {
                $handle = fopen('php://output', 'w');

                // Excelで日本語が文字化けしにくいようにBOMを付ける
                fwrite($handle, "\xEF\xBB\xBF");

                fputcsv($handle, [
                    '日付',
                    '出勤',
                    '退勤',
                    '休憩',
                    '合計',
                ]);

                for (
                    $day = $startOfMonth->copy();
                    $day->lte($endOfMonth);
                    $day->addDay()
                ) {
                    $attendance = $attendanceRecords->get(
                        $day->format('Y-m-d')
                    );

                    $totalBreakSeconds = 0;
                    $totalWorkSeconds = null;

                    if ($attendance) {
                        foreach ($attendance->attendanceBreaks as $break) {
                            if ($break->break_in && $break->break_out) {
                                $breakIn = Carbon::parse($break->break_in);
                                $breakOut = Carbon::parse($break->break_out);

                                $totalBreakSeconds +=
                                    $breakOut->diffInSeconds($breakIn);
                            }
                        }

                        if ($attendance->clock_in && $attendance->clock_out) {
                            $clockIn = Carbon::parse($attendance->clock_in);
                            $clockOut = Carbon::parse($attendance->clock_out);

                            $totalWorkSeconds = max(
                                0,
                                $clockOut->diffInSeconds($clockIn)
                                - $totalBreakSeconds
                            );
                        }
                    }

                    $formattedDate = $day->format('m/d')
                        . '('
                        . ['日', '月', '火', '水', '木', '金', '土'][$day->dayOfWeek]
                        . ')';

                    $clockInTime = $attendance?->clock_in
                        ? substr((string) $attendance->clock_in, 0, 5)
                        : '';

                    $clockOutTime = $attendance?->clock_out
                        ? substr((string) $attendance->clock_out, 0, 5)
                        : '';

                    $breakTime = $totalBreakSeconds > 0
                        ? gmdate('H:i:s', $totalBreakSeconds)
                        : '';

                    $workTime = $totalWorkSeconds !== null
                        ? gmdate('H:i:s', $totalWorkSeconds)
                        : '';

                    fputcsv($handle, [
                        $formattedDate,
                        $clockInTime,
                        $clockOutTime,
                        $breakTime,
                        $workTime,
                    ]);
                }

                fclose($handle);
            },
            $fileName,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }
}
