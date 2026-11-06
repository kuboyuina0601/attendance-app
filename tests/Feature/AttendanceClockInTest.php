<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceClockInTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_clock_in(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 09:15:00'));

        try {
            $user = User::factory()->create([
                'admin_status' => false,
            ]);

            $response = $this->actingAs($user)->get('/attendance');

            $response->assertStatus(200);
            $response->assertSee('出勤');

            $this->post('/attendance', [
                'action' => 'clock_in',
            ]);

            $response = $this->get('/attendance');

            $response->assertStatus(200);
            $response->assertSee('出勤中');

            $this->assertDatabaseHas('attendance_records', [
                'user_id' => $user->id,
                'work_date' => '2026-10-28',
                'clock_in' => '09:15:00',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_clock_in_button_is_not_displayed_after_clock_out(): void
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
        $response->assertDontSee('value="clock_in"', false);
    }

    public function test_clock_in_time_is_displayed_on_attendance_list(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 09:15:00'));

        try {
            $user = User::factory()->create([
                'admin_status' => false,
            ]);

            $this->actingAs($user)->post('/attendance', [
                'action' => 'clock_in',
            ]);

            $response = $this->get('/attendance/list');

            $response->assertStatus(200);
            $response->assertSee('09:15');
        } finally {
            Carbon::setTestNow();
        }
    }
}
