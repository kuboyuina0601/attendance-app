<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StampCorrectionRequest>
 */
class StampCorrectionRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $newClockIn = fake()->dateTimeBetween('08:00:00', '10:00:00');

        $newClockOut = Carbon::instance($newClockIn)
            ->addHours(fake()->numberBetween(8, 10));

        return [
            'attendance_id' => Attendance::factory(),
            'new_clock_in' => $newClockIn->format('H:i:s'),
            'new_clock_out' => $newClockOut->format('H:i:s'),
            'comment' => fake()->sentence(),
            'approval_status' => '承認待ち',
            'application_date' => now()->toDateString(),
            'approved_at' => null,
        ];
    }
}
