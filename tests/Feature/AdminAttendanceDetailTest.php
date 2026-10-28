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

    /**
     * 管理者が勤怠詳細画面を表示できる。
     */
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
            ->get('/admin/attendance/' . $attendance->id);

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

    /**
     * 出勤時間が退勤時間以降の場合はエラーになる。
     */
    public function test_admin_cannot_update_attendance_when_clock_in_is_after_clock_out(): void
    {
        [$admin, $attendance] = $this->createAdminAndAttendance();

        $response = $this->actingAs($admin)
            ->post('/attendance/' . $attendance->id, [
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

    /**
     * 休憩終了が退勤時間以降の場合はエラーになる。
     */
    public function test_admin_cannot_update_attendance_when_break_ends_after_clock_out(): void
    {
        [$admin, $attendance] = $this->createAdminAndAttendance();

        $response = $this->actingAs($admin)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['17:00'],
                'new_break_out' => ['18:00'],
                'comment' => '修正テスト',
            ]);

        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    /**
     * 休憩終了が休憩開始以前の場合はエラーになる。
     */
    public function test_admin_cannot_update_attendance_when_break_out_is_before_break_in(): void
    {
        [$admin, $attendance] = $this->createAdminAndAttendance();

        $response = $this->actingAs($admin)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['13:00'],
                'new_break_out' => ['12:00'],
                'comment' => '修正テスト',
            ]);

        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間が不適切な値です',
        ]);
    }

    /**
     * 備考が空欄の場合はエラーになる。
     */
    public function test_admin_cannot_update_attendance_without_comment(): void
    {
        [$admin, $attendance] = $this->createAdminAndAttendance();

        $response = $this->actingAs($admin)
            ->post('/attendance/' . $attendance->id, [
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
     * 正しい内容で勤怠を修正できる。
     */
    public function test_admin_can_update_attendance_with_valid_data(): void
    {
        [$admin, $attendance] = $this->createAdminAndAttendance();

        $response = $this->actingAs($admin)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '08:30',
                'new_clock_out' => '17:30',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['12:45'],
                'comment' => '管理者による修正',
            ]);

        $response->assertRedirect('/admin/attendance/' . $attendance->id);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '08:30',
            'clock_out' => '17:30',
            'comment' => '管理者による修正',
        ]);

        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_in' => '12:00',
            'break_out' => '12:45',
        ]);
    }

    /**
     * 管理者とテスト対象の勤怠データを作成する。
     */
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
?>