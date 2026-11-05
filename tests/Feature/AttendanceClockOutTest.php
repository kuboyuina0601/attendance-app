<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceClockOutTest extends TestCase
{
    use RefreshDatabase;

    private function createAttendance(User $user): Attendance
    {
        return Attendance::create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in' => '09:00:00',
        ]);
    }

    public function test_user_can_clock_out(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 18:00:00'));

        try {
            $user = User::factory()->create([
                'admin_status' => false,
            ]);

            $this->createAttendance($user);

            $this->actingAs($user);

            $response = $this->get('/attendance');

            $response->assertStatus(200);
            $response->assertSee('退勤');

            $this->post('/attendance', [
                'action' => 'clock_out',
            ]);

            $response = $this->get('/attendance');

            $response->assertStatus(200);
            $response->assertSee('退勤済');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_clock_out_time_is_displayed_on_attendance_list(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 09:00:00'));

        try {
            $user = User::factory()->create([
                'admin_status' => false,
            ]);

            $this->actingAs($user);

            $this->post('/attendance', [
                'action' => 'clock_in',
            ]);

            Carbon::setTestNow(Carbon::parse('2026-10-28 18:00:00'));

            $this->post('/attendance', [
                'action' => 'clock_out',
            ]);

            $response = $this->get('/attendance/list');

            $response->assertStatus(200);
            $response->assertSee('18:00');
        } finally {
            Carbon::setTestNow();
        }
    }
}
