<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAdminAttendanceRequest;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use App\Models\StampCorrectionRequest;

class AdminAttendanceController extends Controller
{
    /**
     * 指定した日付の全ユーザーの勤怠一覧を表示する
     *
     * @param Request $request 表示する日付を受け取るリクエスト
     * @return View 管理者向け勤怠一覧画面
     */
    public function index(Request $request): View
    {
        $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : Carbon::today();

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        $users = User::where('admin_status', false)
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

    /**
     * 指定した勤怠の詳細を表示する
     *
     * @param int $id 表示する勤怠ID
     * @return View 管理者向け勤怠詳細画面
     */
    public function show(int $id): View
    {
        $attendance = Attendance::with([
            'user',
            'attendanceBreaks',
        ])->findOrFail($id);

        $user = $attendance->user;

        abort_if(!$user || $user->admin_status, 404);

        $application = StampCorrectionRequest::where('attendance_id', $id)
            ->where('approval_status', '承認待ち')
            ->exists();

        $clockIn = $attendance->clock_in
            ? Carbon::parse($attendance->clock_in)->format('H:i')
            : '';

        $clockOut = $attendance->clock_out
            ? Carbon::parse($attendance->clock_out)->format('H:i')
            : '';

        $breaks = $attendance->attendanceBreaks
            ->map(function (AttendanceBreak $break): array {
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
            'comment' => $attendance->comment ?? '',
            'application' => $application,
        ];

        return view('admin.admin-detail', compact(
            'attendanceRecord',
            'user'
        ));
    }

    /**
     * 管理者が勤怠情報と休憩情報を修正する
     *
     * @param UpdateAdminAttendanceRequest $request 更新内容を受け取るリクエスト
     * @param int $id 更新対象の勤怠ID
     * @return RedirectResponse 勤怠詳細画面へのリダイレクトレスポンス
     */
    public function update(
        UpdateAdminAttendanceRequest $request,
        int $id
    ): RedirectResponse {
        $attendance = Attendance::with([
            'user',
            'attendanceBreaks',
        ])->findOrFail($id);

        abort_if(!$attendance->user || $attendance->user->admin_status, 404);

        $hasPendingApplication = StampCorrectionRequest::where('attendance_id', $id)
            ->where('approval_status', '承認待ち')
            ->exists();

        if ($hasPendingApplication) {
            return redirect('/admin/attendance/' . $attendance->id);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($attendance, $validated): void {
            $attendance = Attendance::whereKey($attendance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $attendance->update([
                'clock_in' => $validated['new_clock_in'],
                'clock_out' => $validated['new_clock_out'],
                'comment' => $validated['comment'],
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

        return redirect('/admin/attendance/' . $attendance->id);
    }

    /**
     * 秒数を時:分の形式に変換する
     *
     * @param int $seconds 変換する秒数
     * @return string 時:分の形式に変換した時間
     */
    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%d:%02d', $hours, $minutes);
    }
}