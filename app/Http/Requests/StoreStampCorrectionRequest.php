<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStampCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_clock_in' => [
                'required',
                'date_format:H:i',
                'before:new_clock_out',
            ],
            'new_clock_out' => [
                'required',
                'date_format:H:i',
                'after:new_clock_in',
            ],
            'new_break_in' => [
                'nullable',
                'array',
            ],
            'new_break_out' => [
                'nullable',
                'array',
            ],
            'new_break_in.*' => [
                'nullable',
                'date_format:H:i',
            ],
            'new_break_out.*' => [
                'nullable',
                'date_format:H:i',
            ],
            'comment' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_in.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_in.before' => '出勤時間もしくは退勤時間が不適切な値です',

            'new_clock_out.required' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.after' => '出勤時間もしくは退勤時間が不適切な値です',

            'new_break_in.*.date_format' => '休憩時間が不適切な値です',
            'new_break_out.*.date_format' => '休憩時間が不適切な値です',

            'comment.required' => '備考を記入してください',
            'comment.max' => '備考は255文字以内で入力してください',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $clockIn = $this->input('new_clock_in');
            $clockOut = $this->input('new_clock_out');

            // 出退勤時刻の形式が正しい場合だけ、休憩との前後関係を確認する
            $validClockIn = is_string($clockIn)
                && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $clockIn);

            $validClockOut = is_string($clockOut)
                && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $clockOut);

            $breakIns = $this->input('new_break_in', []);
            $breakOuts = $this->input('new_break_out', []);

            if (!is_array($breakIns) || !is_array($breakOuts)) {
                return;
            }

            $indexes = array_unique(array_merge(
                array_keys($breakIns),
                array_keys($breakOuts)
            ));

            foreach ($indexes as $index) {
                $breakIn = $breakIns[$index] ?? null;
                $breakOut = $breakOuts[$index] ?? null;

                // 開始・終了の両方が空欄なら、休憩入力なしとして扱う
                if (blank($breakIn) && blank($breakOut)) {
                    continue;
                }

                // 開始・終了の片方だけ入力されている場合はエラー
                if (blank($breakIn) || blank($breakOut)) {
                    $validator->errors()->add(
                        "new_break_in.$index",
                        '休憩時間が不適切な値です'
                    );

                    continue;
                }

                $validBreakIn = is_string($breakIn)
                    && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $breakIn);

                $validBreakOut = is_string($breakOut)
                    && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $breakOut);

                // 時刻の形式が不正な場合は、形式エラーに任せる
                if (!$validBreakIn || !$validBreakOut) {
                    continue;
                }

                if ($breakOut <= $breakIn) {
                    $validator->errors()->add(
                        "new_break_out.$index",
                        '休憩時間が不適切な値です'
                    );
                }

                if ($validClockIn && $breakIn <= $clockIn) {
                    $validator->errors()->add(
                        "new_break_in.$index",
                        '休憩時間が不適切な値です'
                    );
                }

                if ($validClockOut && $breakOut >= $clockOut) {
                    $validator->errors()->add(
                        "new_break_out.$index",
                        '休憩時間もしくは退勤時間が不適切な値です'
                    );
                }
            }
        });
    }
}
