<?php

namespace Database\Factories;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceBreak>
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

        $breakEnd = \Carbon\Carbon::instance($breakStart)
            ->addMinutes(fake()->numberBetween(30, 90));

        return [
            'attendance_id' => Attendance::factory(),
            'break_start' => $breakStart->format('H:i:s'),
            'break_end' => $breakEnd->format('H:i:s'),
        ];
    }
}