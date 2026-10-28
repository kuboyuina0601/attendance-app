<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function createAttendance(User $user): Attendance
    {
        return Attendance::create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    private function validCorrectionData(): array
    {
        return [
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '打刻時間の修正申請',
        ];
    }

    public function test_clock_in_after_clock_out_is_rejected(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $data = $this->validCorrectionData();
        $data['new_clock_in'] = '19:00';
        $data['new_clock_out'] = '18:00';

        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            $data
        );

        $response->assertSessionHasErrors([
            'new_clock_in',
            'new_clock_out',
        ]);
    }

    public function test_break_after_clock_out_is_rejected(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $data = $this->validCorrectionData();
        $data['new_clock_out'] = '18:00';
        $data['new_break_in'] = ['18:30'];
        $data['new_break_out'] = ['18:45'];

        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            $data
        );

        $response->assertSessionHasErrors();
    }

    public function test_break_out_after_clock_out_is_rejected(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $data = $this->validCorrectionData();
        $data['new_clock_out'] = '18:00';
        $data['new_break_in'] = ['16:00'];
        $data['new_break_out'] = ['18:30'];

        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            $data
        );

        $response->assertSessionHasErrors();
    }

    public function test_comment_is_required(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $data = $this->validCorrectionData();
        $data['comment'] = '';

        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            $data
        );

        $response->assertSessionHasErrors([
            'comment',
        ]);
    }

    public function test_correction_request_is_created_successfully(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            $this->validCorrectionData()
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('stamp_correction_requests', [
            'attendance_id' => $attendance->id,
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'comment' => '打刻時間の修正申請',
            'approval_status' => '承認待ち',
        ]);
    }

    public function test_user_cannot_request_correction_for_another_users_attendance(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $anotherUser = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($anotherUser);

        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            $this->validCorrectionData()
        );

        $response->assertNotFound();
    }

    public function test_original_attendance_is_not_updated_before_approval(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            $this->validCorrectionData()
        );

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }
}
?>