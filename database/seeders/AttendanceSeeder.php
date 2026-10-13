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
        $users = User::where('admin_flag', 0)->get();

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
                    'break_in' => '12:00',
                    'break_out' => '13:00',
                ]);

                $count++;
                $date->subDay();
            }
        }
    }
}