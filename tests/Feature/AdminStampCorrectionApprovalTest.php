<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\StampCorrectionRequest;
use App\Models\StampCorrectionRequestBreak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStampCorrectionApprovalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者が修正申請一覧を表示できる。
     */
    public function test_admin_can_view_stamp_correction_request_list(): void
    {
        [$admin, $user, $attendance] = $this->createAdminUserAndAttendance();

        $application = $this->createCorrectionRequest($attendance);

        $response = $this->actingAs($admin)
            ->get('/stamp_correction_request/list');

        $response->assertOk();
        $response->assertSee($user->name);
        $response->assertSee('承認待ち');
        $response->assertSee('修正申請のテスト');
    }

    /**
     * 管理者が修正申請の詳細を表示できる。
     */
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

    /**
     * 管理者が修正申請を承認できる。
     */
    public function test_admin_can_approve_stamp_correction_request(): void
    {
        [$admin, $user, $attendance] = $this->createAdminUserAndAttendance();

        $application = $this->createCorrectionRequest($attendance);

        $this->actingAs($admin)
            ->post('/stamp_correction_request/approve/' . $application->id);

        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $application->id,
            'approval_status' => '承認済み',
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
        ]);
    }

    /**
     * 承認時に休憩時間も申請内容に更新される。
     */
    public function test_approval_updates_attendance_breaks(): void
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

        $this->actingAs($admin)
            ->post('/stamp_correction_request/approve/' . $application->id);

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
    }

    /**
     * 承認済みの申請は再承認しても勤怠を更新しない。
     */
    public function test_approved_request_cannot_be_approved_again(): void
    {
        [$admin, $user, $attendance] = $this->createAdminUserAndAttendance();

        $application = $this->createCorrectionRequest($attendance);

        $this->actingAs($admin)
            ->post('/stamp_correction_request/approve/' . $application->id);

        $attendance->refresh();

        $this->assertSame('09:30:00', $attendance->clock_in);
        $this->assertSame('18:30:00', $attendance->clock_out);

        $application->refresh();
        $this->assertSame('承認済み', $application->approval_status);

        $this->actingAs($admin)
            ->post('/stamp_correction_request/approve/' . $application->id);

        $attendance->refresh();

        $this->assertSame('09:30:00', $attendance->clock_in);
        $this->assertSame('18:30:00', $attendance->clock_out);
    }

    /**
     * 管理者・一般ユーザー・勤怠データを作成する。
     */
    private function createAdminUserAndAttendance(): array
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '申請テストユーザー',
            'admin_status' => false,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正前の備考',
        ]);

        return [$admin, $user, $attendance];
    }

    /**
     * 勤怠修正申請を作成する。
     */
    private function createCorrectionRequest(Attendance $attendance): StampCorrectionRequest
    {
        return StampCorrectionRequest::factory()->create([
            'attendance_id' => $attendance->id,
            'new_clock_in' => '09:30:00',
            'new_clock_out' => '18:30:00',
            'comment' => '修正申請のテスト',
            'application_date' => '2026-10-21',
            'approval_status' => '承認待ち',
            'approved_at' => null,
        ]);
    }
}
