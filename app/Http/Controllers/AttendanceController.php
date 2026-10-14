<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;

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
            $attendance->attendanceBreaks->contains(function ($break) {
                return $break->break_out === null;
            })
        ) {
            $user->attendance_status = '休憩中';
        } else {
            $user->attendance_status = '出勤中';
        }
        $formattedDate = now()->format('Y年m月d日');
        $formattedTime = now()->format('H:i');
        return view('user.attendance-register', compact(
            'user',
            'formattedDate',
            'formattedTime'
        ));
    }
}