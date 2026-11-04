<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStampCorrectionRequest;
use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use App\Models\StampCorrectionRequestBreak;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Builder;

class StampCorrectionRequestController extends Controller
{
    public function store(
        StoreStampCorrectionRequest $request,
        int $id
    ): RedirectResponse {
        // ログイン中の本人の勤怠だけ申請対象にする
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->findOrFail($id);

        // 同じ勤怠に承認待ちの申請がある場合は、新たに申請させない
        $hasPendingApplication = StampCorrectionRequest::where(
            'attendance_id',
            $attendance->id
        )
            ->where('approval_status', '承認待ち')
            ->exists();

        if ($hasPendingApplication) {
            throw ValidationException::withMessages([
                'comment' => '承認待ちのため修正できません。',
            ]);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($attendance, $validated): void {
            $application = StampCorrectionRequest::create([
                'attendance_id' => $attendance->id,
                'new_clock_in' => $validated['new_clock_in'],
                'new_clock_out' => $validated['new_clock_out'],
                'comment' => $validated['comment'],
                'approval_status' => '承認待ち',
                'application_date' => today()->toDateString(),
            ]);

            $breakIns = $validated['new_break_in'] ?? [];
            $breakOuts = $validated['new_break_out'] ?? [];

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                if (blank($breakIn) && blank($breakOut)) {
                    continue;
                }

                if (blank($breakIn) || blank($breakOut)) {
                    throw ValidationException::withMessages([
                        "new_break_in.$index" => '休憩時間を正しく入力してください。',
                    ]);
                }

                StampCorrectionRequestBreak::create([
                    'stamp_correction_request_id' => $application->id,
                    'new_break_in' => $breakIn,
                    'new_break_out' => $breakOut,
                ]);
            }
        });

        return redirect()->back();
    }

    public function index(): View
    {
        $user = auth()->user();

        $applications = StampCorrectionRequest::with([
            'attendance.user',
            'stampCorrectionRequestBreaks',
        ])
            ->whereHas(
                'attendance',
                function (Builder $query) use ($user): void {
                    $query->where('user_id', $user->id);
                }
            )
            ->orderByRaw("
                CASE
                    WHEN approval_status = '承認待ち' THEN 0
                    ELSE 1
                END ASC
            ")
            ->orderByRaw("
                CASE
                    WHEN approval_status = '承認待ち' THEN created_at
                END ASC
            ")
            ->orderByRaw("
                CASE
                    WHEN approval_status = '承認済み' THEN approved_at
                END DESC
            ")
            ->get();

        $formattedApplications = $applications->map(
            function (StampCorrectionRequest $application): array {
                return [
                    'id' => $application->id,
                    'approval_status' => $application->approval_status,
                    'date' => $application->attendance->work_date->format('Y/m/d'),
                    'comment' => $application->comment,
                    'application_date' => $application->application_date->format('Y/m/d'),
                ];
            }
        );

        return view(
            'user.user-application-list',
            compact('formattedApplications', 'user')
        );
    }

    public function show(int $id): View
    {
        $user = auth()->user();

        // ログイン中の本人の申請だけ取得する
        $application = StampCorrectionRequest::with([
            'attendance.attendanceBreaks',
            'stampCorrectionRequestBreaks',
        ])
            ->whereHas(
                'attendance',
                function (Builder $query) use ($user): void {
                    $query->where('user_id', $user->id);
                }
            )
            ->findOrFail($id);

        $attendance = $application->attendance;

        $breaks = $application->stampCorrectionRequestBreaks
            ->map(function (StampCorrectionRequestBreak $break): array {
                return [
                    'break_in' => substr((string) $break->new_break_in, 0, 5),
                    'break_out' => substr((string) $break->new_break_out, 0, 5),
                ];
            })
            ->values()
            ->all();

        $data = [
            'id' => $attendance->id,
            'year' => $attendance->work_date->format('Y年'),
            'date' => $attendance->work_date->format('m月d日'),
            'clock_in' => substr((string) $application->new_clock_in, 0, 5),
            'clock_out' => substr((string) $application->new_clock_out, 0, 5),
            'breaks' => $breaks,
            'comment' => $application->comment,
            'application' => $application->approval_status === '承認待ち'
                ? $application
                : null,
        ];

        return view('user.user-detail', compact('user', 'data'));
    }
}