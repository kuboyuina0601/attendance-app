<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $user1 = User::where('email', 'user1@example.com')->firstOrFail();
        $user2 = User::where('email', 'user2@example.com')->firstOrFail();

        $currentMonth = Carbon::today()->startOfMonth();

        // user1：過去5か月分、各月15日
        for ($i = 5; $i >= 1; $i--) {
            $month = $currentMonth->copy()->subMonths($i);

            $this->createMonthlyAttendances($user1, $month, 15);
        }

        // user1：当月17日分
        $patterns = [
            ['clock_in' => '09:00:00', 'clock_out' => '18:00:00', 'count' => 10],
            ['clock_in' => '09:00:00', 'clock_out' => '20:00:00', 'count' => 3],
            ['clock_in' => '09:30:00', 'clock_out' => '18:00:00', 'count' => 2],
            ['clock_in' => '09:00:00', 'clock_out' => '17:00:00', 'count' => 1],
            ['clock_in' => '08:00:00', 'clock_out' => '21:00:00', 'count' => 1],
        ];

        $this->createPatternAttendances($user1, $currentMonth, $patterns);

        // user2：当月を含む直近6か月分、各月15日
        for ($i = 5; $i >= 0; $i--) {
            $month = $currentMonth->copy()->subMonths($i);

            $this->createMonthlyAttendances($user2, $month, 15);
        }
    }

    /**
     * 指定した月の平日分の通常勤務データを作成する
     */
    private function createMonthlyAttendances(
        User $user,
        Carbon $month,
        int $count
    ): void {
        $date = $month->copy()->startOfMonth();
        $created = 0;

        while ($created < $count) {
            if ($date->isWeekday()) {
                $this->createAttendance(
                    $user,
                    $date,
                    '09:00:00',
                    '18:00:00'
                );

                $created++;
            }

            $date->addDay();
        }
    }

    /**
     * 指定した勤務パターンで当月分のデータを作成する
     */
    private function createPatternAttendances(
        User $user,
        Carbon $month,
        array $patterns
    ): void {
        $date = $month->copy()->startOfMonth();

        foreach ($patterns as $pattern) {
            for ($i = 0; $i < $pattern['count']; $i++) {
                while ($date->isWeekend()) {
                    $date->addDay();
                }

                $this->createAttendance(
                    $user,
                    $date,
                    $pattern['clock_in'],
                    $pattern['clock_out']
                );

                $date->addDay();
            }
        }
    }

    /**
     * 勤怠データと休憩データを作成・更新する
     */
    private function createAttendance(
        User $user,
        Carbon $date,
        string $clockIn,
        string $clockOut
    ): void {
        $attendance = Attendance::updateOrCreate(
            [
                'user_id' => $user->id,
                'work_date' => $date->format('Y-m-d'),
            ],
            [
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
            ]
        );

        AttendanceBreak::updateOrCreate(
            [
                'attendance_id' => $attendance->id,
            ],
            [
                'break_in' => '12:00:00',
                'break_out' => '13:00:00',
            ]
        );
    }
}
