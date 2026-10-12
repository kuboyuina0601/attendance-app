<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $date = Carbon::today()->subDay();
            $count = 0;

            while ($count < 20) {
                if ($date->isWeekend()) {
                    $date->subDay();
                    continue;
                }

                $attendance = Attendance::factory()->create([
                    'user_id' => $user->id,
                    'work_date' => $date->format('Y-m-d'),
                ]);

                AttendanceBreak::create([
                    'attendance_id' => $attendance->id,
                    'break_start' => '12:00:00',
                    'break_end' => '13:00:00',
                ]);

                $count++;
                $date->subDay();
            }
        }
    }
}