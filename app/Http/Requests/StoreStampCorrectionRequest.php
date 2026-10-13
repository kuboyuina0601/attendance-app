<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStampCorrectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i', 'before:new_clock_out'],
            'new_clock_out' => ['required', 'date_format:H:i', 'after:new_clock_in'],
            'new_break_in' => ['required', 'date_format:H:i', 'after:new_clock_in', 'before:new_clock_out'],
            'new_break_out' => ['required', 'date_format:H:i', 'before:new_clock_out'],
            'comment' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_clock_in.before' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.after' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_break_in.after' => '休憩時間が不適切な値です',
            'new_break_in.before' => '休憩時間が不適切な値です',
            'new_break_out.before' => '休憩時間もしくは退勤時間が不適切な値です',
            'comment.required' => '備考を記入してください',
        ];
    }
}