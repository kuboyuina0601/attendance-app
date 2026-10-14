<?php

namespace App\Http\Controllers;

use App\Models\StampCorrectionRequest;

class AdminStampCorrectionRequestController extends Controller
{
    public function index()
    {
        $applications = StampCorrectionRequest::with([
            'attendance.user',
            'stampCorrectionRequestBreaks',
        ])
            ->orderByDesc('created_at')
            ->get();

        // 既存Bladeの参照形式に合わせる
        foreach ($applications as $application) {
            $application->user = $application->attendance->user;

            $application->AttendanceRecord = (object) [
                'date' => $application->attendance->work_date,
            ];
        }

        return view(
            'admin.admin-application-list',
            compact('applications')
        );
    }
}