<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceBreakTest extends TestCase
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

    public function test_user_can_start_break(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $this->createAttendance($user);

        $this->actingAs($user);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('出勤中');
        $response->assertSee('休憩入');

        $this->post('/attendance', [
            'action' => 'break_in',
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩中');
        $response->assertSee('休憩戻');

        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => Attendance::where('user_id', $user->id)->first()->id,
            'break_out' => null,
        ]);
    }

    public function test_user_can_take_multiple_breaks(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $this->createAttendance($user);

        $this->actingAs($user);

        $this->post('/attendance', [
            'action' => 'break_in',
        ]);

        $this->post('/attendance', [
            'action' => 'break_out',
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('出勤中');
        $response->assertSee('休憩入');
    }

    public function test_user_can_end_break(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => null,
        ]);

        $this->actingAs($user);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩中');
        $response->assertSee('休憩戻');

        $this->post('/attendance', [
            'action' => 'break_out',
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('出勤中');
        $response->assertSee('休憩入');

        $this->assertDatabaseMissing('attendance_breaks', [
            'id' => AttendanceBreak::where('attendance_id', $attendance->id)->first()->id,
            'break_out' => null,
        ]);
    }

    public function test_user_can_return_from_break_multiple_times(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $this->createAttendance($user);

        $this->actingAs($user);

        $this->post('/attendance', [
            'action' => 'break_in',
        ]);

        $this->post('/attendance', [
            'action' => 'break_out',
        ]);

        $this->post('/attendance', [
            'action' => 'break_in',
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩中');
        $response->assertSee('休憩戻');

        $this->assertDatabaseCount('attendance_breaks', 2);
    }

    public function test_break_times_are_displayed_on_attendance_list(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00'));

        try {
            $user = User::factory()->create([
                'admin_status' => false,
            ]);

            $this->createAttendance($user);

            $this->actingAs($user);

            $this->post('/attendance', [
                'action' => 'break_in',
            ]);

            Carbon::setTestNow(Carbon::parse('2026-10-28 13:00:00'));

            $this->post('/attendance', [
                'action' => 'break_out',
            ]);

            $response = $this->get('/attendance/list');

            $response->assertStatus(200);
            $response->assertSee('1:00');

            $this->assertDatabaseHas('attendance_breaks', [
                'attendance_id' => Attendance::where('user_id', $user->id)->first()->id,
                'break_in' => '12:00:00',
                'break_out' => '13:00:00',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }
}
