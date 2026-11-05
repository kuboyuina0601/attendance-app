<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_is_off_duty_when_no_attendance_exists(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('勤務外');
    }

    public function test_status_is_working_when_clocked_in(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => today(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('出勤中');
    }

    public function test_status_is_on_break_when_on_break(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => today(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩中');
    }

    public function test_status_is_finished_when_clocked_out(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => today(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('退勤済');
    }
}
