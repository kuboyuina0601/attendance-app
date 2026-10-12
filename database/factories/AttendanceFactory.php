<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clockIn = fake()->dateTimeBetween('08:00:00', '10:00:00');
        //退勤時間が必ず出勤時間より後になる処理
        $clockOut = Carbon::instance($clockIn)
            ->addHours(fake()->numberBetween(8, 10));

        return [
            'user_id' => User::factory(),
            'work_date' => fake()->date(),
            'clock_in' => $clockIn->format('H:i:s'),
            'clock_out' => $clockOut->format('H:i:s'),
            'note' => null,
        ];
    }
}