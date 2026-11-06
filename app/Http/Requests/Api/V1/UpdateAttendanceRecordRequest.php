<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attendanceRecord = $this->route('attendanceRecord');

        return $this->user()?->can('update', $attendanceRecord) ?? false;
    }

    public function rules(): array
    {
        $attendanceRecord = $this->route('attendanceRecord');

        return [
            'date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendance_records', 'work_date')
                    ->where('user_id', $attendanceRecord?->user_id)
                    ->ignore($attendanceRecord?->id),
            ],
            'clock_in' => [
                'sometimes',
                'required',
                'date_format:H:i:s',
            ],
            'clock_out' => [
                'sometimes',
                'nullable',
                'date_format:H:i:s',
            ],
            'comment' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (
                ! $this->exists('clock_in') &&
                ! $this->exists('clock_out')
            ) {
                return;
            }

            if (
                $validator->errors()->has('clock_in') ||
                $validator->errors()->has('clock_out')
            ) {
                return;
            }

            $attendanceRecord = $this->route('attendanceRecord');

            $clockIn = $this->input(
                'clock_in',
                $attendanceRecord?->clock_in
            );

            $clockOut = $this->input(
                'clock_out',
                $attendanceRecord?->clock_out
            );

            if (
                $clockIn &&
                $clockOut &&
                $clockOut <= $clockIn
            ) {
                $validator->errors()->add(
                    'clock_out',
                    '退勤時刻は出勤時刻より後の時刻を指定してください。'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'date.required' => '勤怠日は必須です。',
            'date.date_format' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
            'date.unique' => 'この日付の勤怠は既に登録されています。',
            'clock_in.required' => '出勤時刻は必須です。',
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',
            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }
}
