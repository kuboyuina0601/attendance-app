<?php

namespace App\Http\Controllers;

use App\Models\StampCorrectionRequest;
use App\Models\AttendanceBreak;
use Illuminate\Support\Facades\DB;

class AdminStampCorrectionRequestController extends Controller
{
    public function index()
    {
        $applications = StampCorrectionRequest::with([
            'attendance.user',
            'stampCorrectionRequestBreaks',
        ])->orderByDesc('created_at')->get();

        foreach ($applications as $application) {
            $application->user = $application->attendance->user;
            $application->AttendanceRecord = (object) [
                'date' => $application->attendance->work_date,
            ];
        }

        return view('admin.admin-application-list', compact('applications'));
    }

    public function edit($stamp_correction_request_id)
    {
        $application = StampCorrectionRequest::with([
            'attendance.user',
            'stampCorrectionRequestBreaks',
        ])->findOrFail($stamp_correction_request_id);

        $user = $application->attendance->user;

        $application->new_date = $application->attendance->work_date;
        $application->new_clock_in = \Carbon\Carbon::parse($application->new_clock_in)->format('H:i');
        $application->new_clock_out = \Carbon\Carbon::parse($application->new_clock_out)->format('H:i');

        $application->setRelation(
            'proposalBreaks',
            $application->stampCorrectionRequestBreaks->map(function ($break) {
                return (object) [
                    'break_in' => $break->new_break_in,
                    'break_out' => $break->new_break_out,
                ];
            })
        );

        return view('admin.admin-application-detail', compact(
            'application',
            'user'
        ));
    }

    public function update($attendance_correct_request_id)
    {
        $application = StampCorrectionRequest::with('stampCorrectionRequestBreaks')
            ->findOrFail($attendance_correct_request_id);

        if ($application->approval_status !== '承認待ち') {
            return redirect()->back();
        }

        DB::transaction(function () use ($application) {
            $application = StampCorrectionRequest::whereKey($application->id)
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
            ]);

            $attendance->attendanceBreaks()->delete();

            foreach ($application->stampCorrectionRequestBreaks as $break) {
                AttendanceBreak::create([
                    'attendance_id' => $attendance->id,
                    'break_in' => $break->new_break_in,
                    'break_out' => $break->new_break_out,
                ]);
            }

            $application->update([
                'approval_status' => '承認済み',
            ]);
        });

        return redirect()->back();
    }
}