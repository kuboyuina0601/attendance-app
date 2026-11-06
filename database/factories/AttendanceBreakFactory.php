<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceBreak>
 */
class AttendanceBreakFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $breakStart = fake()->dateTimeBetween('12:00:00', '14:00:00');

        $breakEnd = Carbon::instance($breakStart)
            ->addMinutes(fake()->numberBetween(30, 90));

        return [
            'attendance_id' => Attendance::factory(),
            'break_in' => $breakStart->format('H:i:s'),
            'break_out' => $breakEnd->format('H:i:s'),
        ];
    }
}
