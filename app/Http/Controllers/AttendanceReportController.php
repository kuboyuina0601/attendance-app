<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

class AttendanceReportController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $currentMonth = now()->startOfMonth();
        $startMonth = $currentMonth->copy()->subMonths(5);
        $endMonth = $currentMonth->copy()->endOfMonth();

        $attendanceRecords = Attendance::with('attendanceBreaks')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [
                $startMonth->toDateString(),
                $endMonth->toDateString(),
            ])
            ->orderBy('work_date')
            ->get();

        $currentMonthKey = $currentMonth->format('Y-m');

        // 出勤・退勤の両方が記録された勤怠だけを抽出
        $completedAttendanceRecords = $attendanceRecords
            ->filter(
                fn (Attendance $attendance): bool => $attendance->clock_in && $attendance->clock_out
            );

        // 勤怠データを集計用の形式に変換
        $attendanceSummaries = $completedAttendanceRecords
            ->map(function (Attendance $attendance) use ($currentMonthKey): array {
                $clockIn = Carbon::parse($attendance->clock_in);
                $clockOut = Carbon::parse($attendance->clock_out);

                // 休憩時間の合計（秒）
                $breakSeconds = $attendance->attendanceBreaks
                    ->filter(
                        fn (AttendanceBreak $break): bool => $break->break_in && $break->break_out
                    )
                    ->sum(function (AttendanceBreak $break): int {
                        $breakIn = Carbon::parse($break->break_in);
                        $breakOut = Carbon::parse($break->break_out);

                        return $breakOut->diffInSeconds($breakIn);
                    });

                // 実労働時間（秒）
                $workSeconds = max(
                    0,
                    $clockOut->diffInSeconds($clockIn) - $breakSeconds
                );

                // 8時間を超えた分を残業時間として計算
                $overtimeSeconds = max(
                    0,
                    $workSeconds - (8 * 60 * 60)
                );

                return [
                    'month_key' => $attendance->work_date->format('Y-m'),
                    'is_current_month' => $attendance->work_date->format('Y-m') === $currentMonthKey,
                    'clock_in' => $clockIn->format('H:i:s'),
                    'clock_out' => $clockOut->format('H:i:s'),
                    'work_seconds' => $workSeconds,
                    'work_minutes' => intdiv($workSeconds, 60),
                    'overtime_minutes' => intdiv($overtimeSeconds, 60),
                ];
            })
            ->values();

        // 基本サマリー（単位：分）
        $totalWorkMinutes = $attendanceSummaries->sum('work_minutes');

        $totalOvertimeMinutes = $attendanceSummaries->sum('overtime_minutes');

        $workedDays = $attendanceSummaries->count();

        $averageWorkMinutes = $workedDays > 0
            ? (int) round($totalWorkMinutes / $workedDays)
            : 0;

        // 月ごとに勤怠情報を分類
        $monthlyGroups = $attendanceSummaries->groupBy('month_key');

        // 当月を含む過去6か月の月次推移
        $monthlyTrend = collect(range(0, 5))
            ->map(function (int $i) use ($startMonth, $monthlyGroups): array {
                $month = $startMonth->copy()->addMonths($i);
                $monthKey = $month->format('Y-m');

                $monthRecords = $monthlyGroups->get($monthKey, collect());

                return [
                    'month' => $month->format('Y/m'),
                    'work_minutes' => $monthRecords->sum('work_minutes'),
                    'overtime_minutes' => $monthRecords->sum('overtime_minutes'),
                ];
            })
            ->values();

        // 今月の異常検知回数
        $currentMonthRecords = $attendanceSummaries
            ->filter(
                fn (array $record): bool => $record['is_current_month']
            );

        // 09:00より後に出勤した場合は遅刻
        $lateCount = $currentMonthRecords
            ->filter(
                fn (array $record): bool => $record['clock_in'] > '09:00:00'
            )
            ->count();

        // 18:00より前に退勤した場合は早退
        $earlyLeaveCount = $currentMonthRecords
            ->filter(
                fn (array $record): bool => $record['clock_out'] < '18:00:00'
            )
            ->count();

        // 実労働時間が10時間を超えた場合は長時間労働
        $longWorkCount = $currentMonthRecords
            ->filter(
                fn (array $record): bool => $record['work_seconds'] > (10 * 60 * 60)
            )
            ->count();

        $summary = [
            'total_work_minutes' => $totalWorkMinutes,
            'total_overtime_minutes' => $totalOvertimeMinutes,
            'avg_work_minutes' => $averageWorkMinutes,
        ];

        $anomalies = [
            'late_count' => $lateCount,
            'early_leave_count' => $earlyLeaveCount,
            'long_work_count' => $longWorkCount,
        ];

        return view('reports.index', [
            'summary' => $summary,
            'monthlyTrend' => $monthlyTrend->all(),
            'anomalies' => $anomalies,
        ]);
    }
}
