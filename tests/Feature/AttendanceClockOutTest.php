<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_out',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
            'clock_out' => null,
        ]);
    }

    public function test_user_cannot_clock_out_during_break(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $attendance->attendanceBreaks()->create([
            'break_in' => '12:00:00',
            'break_out' => null,
        ]);

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_out',
        ]);

        $response->assertSessionHasErrors(['action']);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_out' => null,
        ]);
    }
}
?>