<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\StampCorrectionRequestBreak;
use App\Models\AttendanceBreak;

class AdminStampCorrectionApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_pending_stamp_correction_requests(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        [$user1, $attendance1] = $this->createUserAndAttendance('申請ユーザーA');
        [$user2, $attendance2] = $this->createUserAndAttendance('申請ユーザーB');

        $this->createCorrectionRequest($attendance1, '承認待ち');
        $this->createCorrectionRequest($attendance2, '承認待ち');

        $response = $this->actingAs($admin)
            ->get('/stamp_correction_request/list');

        $response->assertOk();
        $response->assertSee($user1->name);
        $response->assertSee($user2->name);
        $response->assertSee('修正申請のテスト');
    }

    public function test_admin_can_view_all_approved_stamp_correction_requests(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        [$user1, $attendance1] = $this->createUserAndAttendance('承認済みユーザーA');
        [$user2, $attendance2] = $this->createUserAndAttendance('承認済みユーザーB');

        $this->createCorrectionRequest($attendance1, '承認済み');
        $this->createCorrectionRequest($attendance2, '承認済み');

        $response = $this->actingAs($admin)
            ->get('/stamp_correction_request/list');

        $response->assertOk();
        $response->assertSee($user1->name);
        $response->assertSee($user2->name);
        $response->assertSee('承認済み');
        $response->assertSee('修正申請のテスト');
    }

    public function test_admin_can_view_stamp_correction_request_detail(): void
    {
        [$admin, $user, $attendance] = $this->createAdminUserAndAttendance();

        $application = $this->createCorrectionRequest($attendance);

        $response = $this->actingAs($admin)
            ->get('/stamp_correction_request/approve/' . $application->id);

        $response->assertOk();
        $response->assertSee($user->name);
        $response->assertSee('09:30');
        $response->assertSee('18:30');
        $response->assertSee('修正申請のテスト');
    }

    public function test_admin_can_approve_stamp_correction_request(): void
    {
        [$admin, $user, $attendance] = $this->createAdminUserAndAttendance();

        $application = $this->createCorrectionRequest($attendance);

        $response = $this->actingAs($admin)
            ->post('/stamp_correction_request/approve/' . $application->id);

        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $application->id,
            'approval_status' => '承認済み',
        ]);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
        ]);
    }

    public function test_approval_updates_attendance_breaks_and_approval_timestamp(): void
    {
        [$admin, $user, $attendance] = $this->createAdminUserAndAttendance();

        AttendanceBreak::factory()->create([
            'attendance_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $application = $this->createCorrectionRequest($attendance);

        StampCorrectionRequestBreak::factory()->create([
            'stamp_correction_request_id' => $application->id,
            'new_break_in' => '12:30:00',
            'new_break_out' => '13:15:00',
        ]);

        $response = $this->actingAs($admin)
            ->post('/stamp_correction_request/approve/' . $application->id);

        $response->assertRedirect();

        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_in' => '12:30:00',
            'break_out' => '13:15:00',
        ]);

        $this->assertDatabaseMissing('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $application->id,
            'approval_status' => '承認済み',
        ]);

        $this->assertNotNull($application->fresh()->approved_at);
    }

    private function createAdminUserAndAttendance(): array
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        [$user, $attendance] = $this->createUserAndAttendance('申請テストユーザー');

        return [$admin, $user, $attendance];
    }

    private function createUserAndAttendance(string $name): array
    {
        $user = User::factory()->create([
            'name' => $name,
            'admin_status' => false,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正前の備考',
        ]);

        return [$user, $attendance];
    }

    private function createCorrectionRequest(
        Attendance $attendance,
        string $approvalStatus = '承認待ち'
    ): StampCorrectionRequest {
        return StampCorrectionRequest::factory()->create([
            'attendance_id' => $attendance->id,
            'new_clock_in' => '09:30:00',
            'new_clock_out' => '18:30:00',
            'comment' => '修正申請のテスト',
            'application_date' => '2026-10-21',
            'approval_status' => $approvalStatus,
            'approved_at' => $approvalStatus === '承認済み' ? '2026-10-22 10:00:00' : null,
        ]);
    }
}