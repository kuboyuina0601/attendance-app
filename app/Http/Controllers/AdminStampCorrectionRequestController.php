<?php

namespace App\Http\Controllers;

use App\Models\StampCorrectionRequest;
use App\Models\StampCorrectionRequestBreak;
use App\Models\AttendanceBreak;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AdminStampCorrectionRequestController extends Controller
{
    /**
     * 勤怠修正申請の一覧を表示する
     *
     * @return View 勤怠修正申請一覧画面
     */
    public function index(): View
    {
        $applications = StampCorrectionRequest::with([
            'attendance.user',
            'stampCorrectionRequestBreaks',
        ])
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
            ->get()
            ->map(function ($application) {
                $application->user = $application->attendance->user;

                $application->AttendanceRecord = (object) [
                    'date' => $application->attendance->work_date,
                ];

                return $application;
            });

        return view('admin.admin-application-list', compact('applications'));
    }

    /**
     * 指定した勤怠修正申請の詳細を表示する
     *
     * @param int $stamp_correction_request_id 表示する勤怠修正申請ID
     * @return View 勤怠修正申請詳細画面
     */
    public function show(int $stamp_correction_request_id): View
    {
        $application = StampCorrectionRequest::with([
            'attendance.user',
            'stampCorrectionRequestBreaks',
        ])->findOrFail($stamp_correction_request_id);

        $user = $application->attendance?->user;

        abort_if(!$user || $user->admin_status, 404);

        $application->new_date = $application->attendance->work_date;
        $application->new_clock_in = \Carbon\Carbon::parse($application->new_clock_in)->format('H:i');
        $application->new_clock_out = \Carbon\Carbon::parse($application->new_clock_out)->format('H:i');

        $application->setRelation(
            'proposalBreaks',
            $application->stampCorrectionRequestBreaks->map(
                function (StampCorrectionRequestBreak $break): object {
                    return (object) [
                        'break_in' => $break->new_break_in,
                        'break_out' => $break->new_break_out,
                    ];
                }
            )
        );

        return view('admin.admin-application-detail', compact(
            'application',
            'user'
        ));
    }

    /**
     * 勤怠修正申請を承認し、勤怠情報を更新する
     *
     * @param int $stamp_correction_request_id 承認する勤怠修正申請ID
     * @return RedirectResponse 前の画面へのリダイレクトレスポンス
     */
    public function update(
        int $stamp_correction_request_id
    ): RedirectResponse {
        $application = StampCorrectionRequest::findOrFail(
            $stamp_correction_request_id
        );

        if ($application->approval_status !== '承認待ち') {
            return redirect()->back();
        }

        DB::transaction(function () use ($application): void {
            $application = StampCorrectionRequest::whereKey($application->id)
                ->with('stampCorrectionRequestBreaks')
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->approval_status !== '承認待ち') {
                return;
            }

            $attendance = $application->attendance()
                ->lockForUpdate()
                ->firstOrFail();

            $attendance->update([
                'clock_in' => $application->new_clock_in,
                'clock_out' => $application->new_clock_out,
                'comment' => $application->comment,
            ]);

            $attendance->attendanceBreaks()->delete();

            $application->stampCorrectionRequestBreaks->each(
                function ($break) use ($attendance): void {
                    AttendanceBreak::create([
                        'attendance_id' => $attendance->id,
                        'break_in' => $break->new_break_in,
                        'break_out' => $break->new_break_out,
                    ]);
                }
            );

            $application->update([
                'approval_status' => '承認済み',
                'approved_at' => now(),
            ]);
        });

        return redirect()->back();
    }
}