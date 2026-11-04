<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),

            'user_name' => $this->whenLoaded(
                'user',
                fn() => $this->user->name
            ),

            'date' => $this->work_date?->format('Y-m-d'),
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,

            'total_time' => $this->calculateTotalWorkTime(),
            'total_break_time' => $this->calculateTotalBreakTime(),

            'comment' => $this->comment,

            'breaks' => $this->whenLoaded(
                'attendanceBreaks',
                fn() => $this->attendanceBreaks->map(fn($break) => [
                    'id' => $break->id,
                    'break_in' => $break->break_in,
                    'break_out' => $break->break_out,
                ])->values()
            ),

            'applications' => $this->whenLoaded(
                'stampCorrectionRequests',
                fn() => $this->stampCorrectionRequests->map(fn($application) => [
                    'id' => $application->id,
                ])->values()
            ),
        ];
    }

    private function calculateTotalWorkTime(): ?string
    {
        if (!$this->clock_in || !$this->clock_out) {
            return null;
        }

        $start = strtotime($this->clock_in);
        $end = strtotime($this->clock_out);

        if ($end < $start) {
            $end += 24 * 60 * 60;
        }

        $breakSeconds = $this->calculateBreakSeconds();
        $workSeconds = max(0, $end - $start - $breakSeconds);

        return sprintf(
            '%02d:%02d',
            intdiv($workSeconds, 3600),
            intdiv($workSeconds % 3600, 60)
        );
    }

    private function calculateTotalBreakTime(): string
    {
        $seconds = $this->calculateBreakSeconds();

        return sprintf(
            '%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60)
        );
    }

    private function calculateBreakSeconds(): int
    {
        if (!$this->relationLoaded('attendanceBreaks')) {
            return 0;
        }

        return $this->attendanceBreaks->sum(function ($break): int {
            if (!$break->break_in || !$break->break_out) {
                return 0;
            }

            $start = strtotime($break->break_in);
            $end = strtotime($break->break_out);

            if ($end < $start) {
                $end += 24 * 60 * 60;
            }

            return $end - $start;
        });
    }
}