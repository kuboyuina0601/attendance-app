<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_attendance_detail(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '勤怠詳細テストユーザー',
            'admin_status' => false,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '通常勤務',
        ]);

        AttendanceBreak::factory()->create([
            'attendance_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/'.$attendance->id);

        $response->assertOk();
        $response->assertSee('勤怠詳細テストユーザー');
        $response->assertSee('2026年');
        $response->assertSee('10月20日');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('12:00');
        $response->assertSee('13:00');
        $response->assertSee('通常勤務');
    }

    public function test_admin_cannot_update_attendance_when_clock_in_is_after_clock_out(): void
    {
        [$admin, $attendance] = $this->createAdminAndAttendance();

        $response = $this->actingAs($admin)
            ->post('/admin/attendance/'.$attendance->id, [
                'new_clock_in' => '18:00',
                'new_clock_out' => '09:00',
                'comment' => '修正テスト',
                'new_break_in' => [''],
                'new_break_out' => [''],
            ]);

        $response->assertSessionHasErrors([
            'new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);
    }

    public function test_admin_cannot_update_attendance_when_break_in_is_after_clock_out(): void
    {
        [$admin, $attendance] = $this->createAdminAndAttendance();

        $response = $this->actingAs($admin)
            ->post('/admin/attendance/'.$attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['19:00'],
                'new_break_out' => ['19:30'],
                'comment' => '修正テスト',
            ]);

        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です',
        ]);
    }

    public function test_admin_cannot_update_attendance_when_break_out_is_after_clock_out(): void
    {
        [$admin, $attendance] = $this->createAdminAndAttendance();

        $response = $this->actingAs($admin)
            ->post('/admin/attendance/'.$attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['17:00'],
                'new_break_out' => ['19:00'],
                'comment' => '修正テスト',
            ]);

        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    public function test_admin_cannot_update_attendance_without_comment(): void
    {
        [$admin, $attendance] = $this->createAdminAndAttendance();

        $response = $this->actingAs($admin)
            ->post('/admin/attendance/'.$attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => [''],
                'new_break_out' => [''],
                'comment' => '',
            ]);

        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください',
        ]);
    }

    /**
     * 管理者が勤怠情報を正常に修正できる
     */
    public function test_admin_can_update_attendance_successfully(): void
    {
        [$admin, $attendance] = $this->createAdminAndAttendance();

        $response = $this->actingAs($admin)
            ->post('/admin/attendance/'.$attendance->id, [
                'new_clock_in' => '08:30',
                'new_clock_out' => '19:00',
                'new_break_in' => ['12:30'],
                'new_break_out' => ['13:30'],
                'comment' => '修正後の備考',
            ]);

        $response->assertRedirect('/admin/attendance/'.$attendance->id);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '08:30',
            'clock_out' => '19:00',
            'comment' => '修正後の備考',
        ]);

        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_in' => '12:30',
            'break_out' => '13:30',
        ]);
    }

    private function createAdminAndAttendance(): array
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正前の備考',
        ]);

        AttendanceBreak::factory()->create([
            'attendance_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        return [$admin, $attendance];
    }
}
