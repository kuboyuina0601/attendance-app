<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\StampCorrectionRequestBreak;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendanceController extends Controller
{
    /**
     * 勤怠登録画面を表示する
     *
     * @return View 勤怠登録画面
     */
    public function create(): View
    {
        $user = auth()->user();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', today())
            ->with('attendanceBreaks')
            ->first();

        if (!$attendance) {
            $user->attendance_status = '勤務外';
        } elseif ($attendance->clock_out) {
            $user->attendance_status = '退勤済';
        } elseif (
            $attendance->attendanceBreaks->contains(
                fn(AttendanceBreak $break): bool => $break->break_out === null
            )
        ) {
            $user->attendance_status = '休憩中';
        } else {
            $user->attendance_status = '出勤中';
        }

        $formattedDate = now()->format('Y年m月d日');
        $formattedTime = now()->format('H:i');

        return view(
            'user.attendance-register',
            compact('user', 'formattedDate', 'formattedTime')
        );
    }

    /**
     * 出勤・退勤・休憩の打刻を登録する
     *
     * @param Request $request 打刻操作の種類を受け取るリクエスト
     * @return RedirectResponse 前の画面へのリダイレクトレスポンス
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'action' => ['required', 'in:clock_in,clock_out,break_in,break_out'],
        ]);

        $user = $request->user();
        $action = $request->input('action');
        $now = now();

        DB::transaction(function () use ($user, $action, $now): void {
            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('work_date', $now->toDateString())
                ->lockForUpdate()
                ->first();

            if ($action === 'clock_in') {
                if ($attendance) {
                    throw ValidationException::withMessages([
                        'action' => '本日の出勤はすでに登録されています。',
                    ]);
                }

                Attendance::create([
                    'user_id' => $user->id,
                    'work_date' => $now->toDateString(),
                    'clock_in' => $now->format('H:i:s'),
                ]);

                return;
            }

            if (!$attendance) {
                throw ValidationException::withMessages([
                    'action' => '先に出勤を登録してください。',
                ]);
            }

            if ($attendance->clock_out) {
                throw ValidationException::withMessages([
                    'action' => '本日の勤怠はすでに終了しています。',
                ]);
            }

            $openBreak = $attendance->attendanceBreaks()
                ->whereNull('break_out')
                ->lockForUpdate()
                ->first();

            if ($action === 'break_in') {
                if ($openBreak) {
                    throw ValidationException::withMessages([
                        'action' => 'すでに休憩中です。',
                    ]);
                }

                AttendanceBreak::create([
                    'attendance_id' => $attendance->id,
                    'break_in' => $now->format('H:i:s'),
                ]);

                return;
            }

            if ($action === 'break_out') {
                if (!$openBreak) {
                    throw ValidationException::withMessages([
                        'action' => '終了できる休憩がありません。',
                    ]);
                }

                $openBreak->update([
                    'break_out' => $now->format('H:i:s'),
                ]);

                return;
            }

            if ($action === 'clock_out') {
                if ($openBreak) {
                    throw ValidationException::withMessages([
                        'action' => '休憩を終了してから退勤してください。',
                    ]);
                }

                $attendance->update([
                    'clock_out' => $now->format('H:i:s'),
                ]);
            }
        });

        return redirect()->back();
    }

    /**
     * ログインユーザーの月別勤怠一覧を表示する
     *
     * @param Request $request 表示する年月を受け取るリクエスト
     * @return View 月別勤怠一覧画面
     */
    public function index(Request $request): View
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m'],
        ]);

        $date = $request->filled('date')
            ? Carbon::createFromFormat('!Y-m', $request->input('date'))
            : now()->startOfMonth();

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $attendanceRecords = Attendance::with('attendanceBreaks')
            ->where('user_id', auth()->id())
            ->whereBetween('work_date', [
                $startOfMonth->toDateString(),
                $endOfMonth->toDateString(),
            ])
            ->get()
            ->keyBy(
                fn(Attendance $attendance): string => $attendance->work_date->format('Y-m-d')
            );

        $formattedAttendanceRecords = collect(
            CarbonPeriod::create($startOfMonth, $endOfMonth)
        )->map(function (Carbon $day) use ($attendanceRecords): array {
            $attendance = $attendanceRecords->get($day->format('Y-m-d'));
            $totalBreakSeconds = 0;
            $totalWorkSeconds = null;

            if ($attendance) {
                $totalBreakSeconds = $attendance->attendanceBreaks
                    ->sum(function (AttendanceBreak $break): int {
                        if ($break->break_in && $break->break_out) {
                            $breakIn = Carbon::parse($break->break_in);
                            $breakOut = Carbon::parse($break->break_out);

                            return $breakOut->diffInSeconds($breakIn);
                        }

                        return 0;
                    });

                if ($attendance->clock_in && $attendance->clock_out) {
                    $clockIn = Carbon::parse($attendance->clock_in);
                    $clockOut = Carbon::parse($attendance->clock_out);

                    $totalWorkSeconds = max(
                        0,
                        $clockOut->diffInSeconds($clockIn) - $totalBreakSeconds
                    );
                }
            }

            return [
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
            ];
        });

        return view(
            'user.user-attendance-list',
            compact(
                'date',
                'previousMonth',
                'nextMonth',
                'formattedAttendanceRecords'
            )
        );
    }

    /**
     * 指定した勤怠の詳細を表示する
     *
     * @param int $id 表示する勤怠ID
     * @return View 勤怠詳細画面
     */
    public function show(int $id): View
    {
        $user = auth()->user();

        $attendance = Attendance::with([
            'user',
            'attendanceBreaks',
            'stampCorrectionRequests.stampCorrectionRequestBreaks',
        ])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $application = $attendance->stampCorrectionRequests
            ->where('approval_status', '承認待ち')
            ->first();

        if ($application) {
            $breaks = $application->stampCorrectionRequestBreaks
                ->map(function (StampCorrectionRequestBreak $break): array {
                    return [
                        'break_in' => $break->new_break_in
                            ? substr((string) $break->new_break_in, 0, 5)
                            : '',
                        'break_out' => $break->new_break_out
                            ? substr((string) $break->new_break_out, 0, 5)
                            : '',
                    ];
                })
                ->toArray();

            $clockIn = $application->new_clock_in;
            $clockOut = $application->new_clock_out;
            $comment = $application->comment;
        } else {
            $breaks = $attendance->attendanceBreaks
                ->map(function (AttendanceBreak $break): array {
                    return [
                        'break_in' => $break->break_in
                            ? substr((string) $break->break_in, 0, 5)
                            : '',
                        'break_out' => $break->break_out
                            ? substr((string) $break->break_out, 0, 5)
                            : '',
                    ];
                })
                ->toArray();

            $clockIn = $attendance->clock_in;
            $clockOut = $attendance->clock_out;
            $comment = $attendance->comment ?? '';
        }

        $data = [
            'id' => $attendance->id,
            'year' => $attendance->work_date->format('Y年'),
            'date' => $attendance->work_date->format('n月j日'),
            'clock_in' => $clockIn ? substr((string) $clockIn, 0, 5) : '',
            'clock_out' => $clockOut ? substr((string) $clockOut, 0, 5) : '',
            'breaks' => $breaks,
            'comment' => $comment ?? '',
            'application' => $application,
        ];

        return view('user.user-detail', compact('user', 'data'));
    }
}