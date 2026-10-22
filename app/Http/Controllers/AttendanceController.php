<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function create()
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
                fn($break) => $break->break_out === null
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

    public function store(Request $request)
    {
        $request->validate([
            'action' => ['required', 'in:clock_in,clock_out,break_in,break_out'],
        ]);

        $user = $request->user();
        $action = $request->input('action');
        $now = now();

        DB::transaction(function () use ($user, $action, $now) {
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

    public function index(Request $request)
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
            ->keyBy(fn($attendance) => $attendance->work_date->format('Y-m-d'));

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
                        $clockOut->diffInSeconds($clockIn) - $totalBreakSeconds
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
            'user.user-attendance-list',
            compact(
                'date',
                'previousMonth',
                'nextMonth',
                'formattedAttendanceRecords'
            )
        );
    }


    public function show($id)
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
                ->map(function ($break) {
                    return [
                        'break_in' => $break->new_break_in,
                        'break_out' => $break->new_break_out,
                    ];
                })
                ->toArray();

            $clockIn = $application->new_clock_in;
            $clockOut = $application->new_clock_out;
            $comment = $application->comment;
        } else {
            $breaks = $attendance->attendanceBreaks
                ->map(function ($break) {
                    return [
                        'break_in' => $break->break_in,
                        'break_out' => $break->break_out,
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
            'clock_in' => $clockIn ? substr($clockIn, 0, 5) : '',
            'clock_out' => $clockOut ? substr($clockOut, 0, 5) : '',
            'breaks' => $breaks,
            'comment' => $comment ?? '',
            'application' => $application,
        ];

        return view('user.user-detail', compact('user', 'data'));
    }

}