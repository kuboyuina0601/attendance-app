<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

class AttendanceReportController extends Controller
{
    /**
     * ログインユーザーの過去6か月分の勤怠レポートを表示する
     *
     * @return View 勤怠レポート画面
     */
    public function index(): View
    {
        $user = auth()->user();

        // 当月を含む過去6か月の期間を取得
        $currentMonth = now()->startOfMonth();
        $startMonth = $currentMonth->copy()->subMonths(5);
        $endMonth = $currentMonth->copy()->endOfMonth();

        // ログインユーザーの過去6か月分の勤怠を取得
        $attendances = Attendance::with('attendanceBreaks')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [
                $startMonth->toDateString(),
                $endMonth->toDateString(),
            ])
            ->orderBy('work_date')
            ->get();

        // 基本サマリー（単位：分）
        $totalWorkMinutes = 0;
        $totalOvertimeMinutes = 0;
        $workedDays = 0;

        // 月次推移を初期化（データがない月も0で表示）
        $monthlyTrend = [];

        collect(range(0, 5))->each(function (int $i) use ($startMonth, &$monthlyTrend): void {
            $month = $startMonth->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $monthlyTrend[$key] = [
                'month' => $month->format('Y/m'),
                'work_minutes' => 0,
                'overtime_minutes' => 0,
            ];
        });

        // 今月の異常検知回数
        $lateCount = 0;
        $earlyLeaveCount = 0;
        $longWorkCount = 0;

        $attendances->each(function (Attendance $attendance) use ($currentMonth, &$totalWorkMinutes, &$totalOvertimeMinutes, &$workedDays, &$monthlyTrend, &$lateCount, &$earlyLeaveCount, &$longWorkCount): void {
            // 出勤・退勤の両方が記録された勤怠だけを集計
            if (!$attendance->clock_in || !$attendance->clock_out) {
                return;
            }

            $clockIn = Carbon::parse($attendance->clock_in);
            $clockOut = Carbon::parse($attendance->clock_out);

            // 休憩時間の合計（秒）
            $breakSeconds = $attendance->attendanceBreaks
                ->sum(function ($break): int {
                    if ($break->break_in && $break->break_out) {
                        $breakIn = Carbon::parse($break->break_in);
                        $breakOut = Carbon::parse($break->break_out);

                        return $breakOut->diffInSeconds($breakIn);
                    }

                    return 0;
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

            // 秒から分に変換
            $workMinutes = intdiv($workSeconds, 60);
            $overtimeMinutes = intdiv($overtimeSeconds, 60);

            $totalWorkMinutes += $workMinutes;
            $totalOvertimeMinutes += $overtimeMinutes;
            $workedDays++;

            // 月次推移に反映
            $monthKey = $attendance->work_date->format('Y-m');

            if (isset($monthlyTrend[$monthKey])) {
                $monthlyTrend[$monthKey]['work_minutes'] += $workMinutes;
                $monthlyTrend[$monthKey]['overtime_minutes'] += $overtimeMinutes;
            }

            // 異常検知は今月の勤怠だけを対象にする
            if ($attendance->work_date->format('Y-m') === $currentMonth->format('Y-m')) {
                // 09:00より後に出勤した場合は遅刻
                if ($clockIn->format('H:i:s') > '09:00:00') {
                    $lateCount++;
                }

                // 18:00より前に退勤した場合は早退
                if ($clockOut->format('H:i:s') < '18:00:00') {
                    $earlyLeaveCount++;
                }

                // 実労働時間が10時間を超えた場合は長時間労働
                if ($workSeconds > (10 * 60 * 60)) {
                    $longWorkCount++;
                }
            }
        });

        // 1日あたりの平均労働時間（分）
        $averageWorkMinutes = $workedDays > 0
            ? (int) round($totalWorkMinutes / $workedDays)
            : 0;

        // Bladeの $summary に合わせる
        $summary = [
            'total_work_minutes' => $totalWorkMinutes,
            'total_overtime_minutes' => $totalOvertimeMinutes,
            'avg_work_minutes' => $averageWorkMinutes,
        ];

        // Bladeの $anomalies に合わせる
        $anomalies = [
            'late_count' => $lateCount,
            'early_leave_count' => $earlyLeaveCount,
            'long_work_count' => $longWorkCount,
        ];

        // Bladeの実際の保存場所に合わせる
        return view('reports.index', [
            'summary' => $summary,
            'monthlyTrend' => array_values($monthlyTrend),
            'anomalies' => $anomalies,
        ]);
    }
}