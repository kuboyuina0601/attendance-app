<?php

namespace Database\Factories;

use App\Models\StampCorrectionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StampCorrectionRequestBreak>
 */
class StampCorrectionRequestBreakFactory extends Factory
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
            'stamp_correction_request_id' => StampCorrectionRequest::factory(),
            'new_break_in' => $breakStart->format('H:i:s'),
            'new_break_out' => $breakEnd->format('H:i:s'),
        ];
    }
}
