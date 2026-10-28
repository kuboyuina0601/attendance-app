<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    /**
     * スタッフ一覧に一般ユーザーの名前とメールアドレスが表示される。
     */
    public function test_admin_can_view_staff_list(): void
    {
        $admin = User::factory()->create([
            'name' => '管理者ユーザー',
            'email' => 'admin@example.com',
            'admin_status' => true,
        ]);

        $user1 = User::factory()->create([
            'name' => 'スタッフA',
            'email' => 'staff-a@example.com',
            'admin_status' => false,
        ]);

        $user2 = User::factory()->create([
            'name' => 'スタッフB',
            'email' => 'staff-b@example.com',
            'admin_status' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/staff/list');

        $response->assertOk();
        $response->assertSee('スタッフA');
        $response->assertSee('staff-a@example.com');
        $response->assertSee('スタッフB');
        $response->assertSee('staff-b@example.com');
        $response->assertDontSee('admin@example.com');
    }

    /**
     * スタッフ別勤怠一覧に対象スタッフの勤怠が表示される。
     */
    public function test_admin_can_view_selected_staff_attendance(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '勤怠確認スタッフ',
            'admin_status' => false,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-05',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/staff/' . $user->id . '?date=2026-10');

        $response->assertOk();
        $response->assertSee('勤怠確認スタッフ');
        $response->assertSee('10/05(月)');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    /**
     * スタッフ別勤怠一覧で前月・翌月に対応する勤怠を表示できる。
     */
    public function test_admin_can_view_previous_and_next_month_attendance(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '月移動スタッフ',
            'admin_status' => false,
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-09-15',
            'clock_in' => '08:00:00',
            'clock_out' => '17:00:00',
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-15',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        $previousResponse = $this->actingAs($admin)
            ->get('/admin/attendance/staff/' . $user->id . '?date=2026-09');

        $previousResponse->assertOk();
        $previousResponse->assertSee('08:00');
        $previousResponse->assertSee('17:00');
        $previousResponse->assertDontSee('10:00');

        $nextResponse = $this->actingAs($admin)
            ->get('/admin/attendance/staff/' . $user->id . '?date=2026-11');

        $nextResponse->assertOk();
        $nextResponse->assertSee('10:00');
        $nextResponse->assertSee('19:00');
        $nextResponse->assertDontSee('08:00');
    }

    /**
     * 存在しないスタッフの勤怠一覧は404になる。
     */
    public function test_admin_cannot_view_attendance_for_nonexistent_staff(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/staff/99999?date=2026-10');

        $response->assertNotFound();
    }

    /**
     * 管理者自身をスタッフ別勤怠一覧として表示できない。
     */
    public function test_admin_cannot_view_admin_user_as_staff(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/staff/' . $admin->id . '?date=2026-10');

        $response->assertNotFound();
    }
}
