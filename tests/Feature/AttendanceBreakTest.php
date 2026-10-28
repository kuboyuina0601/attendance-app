<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_out' => null,
        ]);
    }

    public function test_user_can_end_break(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $break = AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => null,
        ]);

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'break_out',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseMissing('attendance_breaks', [
            'id' => $break->id,
            'break_out' => null,
        ]);
    }

    public function test_user_can_take_multiple_breaks(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $this->createAttendance($user);

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_out',
        ]);

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);

        $this->assertDatabaseCount('attendance_breaks', 2);
    }

    public function test_break_times_are_saved(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);

        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_in' => now()->format('H:i:s'),
            'break_out' => null,
        ]);
    }
}
?>