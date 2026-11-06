<?php

namespace App\Http\Requests;

class UpdateAdminAttendanceRequest extends StampCorrectionRequest
{
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'new_clock_in.before' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);
    }
}
