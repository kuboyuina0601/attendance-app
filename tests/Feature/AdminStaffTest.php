<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_staff_list(): void
    {
        $admin = User::factory()->create([
            'name' => '管理者ユーザー',
            'email' => 'admin@example.com',
            'admin_status' => true,
        ]);

        User::factory()->create([
            'name' => 'スタッフA',
            'email' => 'staff-a@example.com',
            'admin_status' => false,
        ]);

        User::factory()->create([
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
    }

    public function test_admin_can_view_selected_staff_attendance(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '勤怠確認スタッフ',
            'admin_status' => false,
        ]);

        Attendance::factory()->create([
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

    public function test_admin_can_view_previous_month_attendance(): void
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
            'work_date' => '2026-10-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/staff/' . $user->id . '?date=2026-09');

        $response->assertOk();
        $response->assertSee('08:00');
        $response->assertSee('17:00');
        $response->assertDontSee('09:00');
        $response->assertDontSee('18:00');
    }

    public function test_admin_can_view_next_month_attendance(): void
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
            'work_date' => '2026-10-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-15',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/staff/' . $user->id . '?date=2026-11');

        $response->assertOk();
        $response->assertSee('10:00');
        $response->assertSee('19:00');
        $response->assertDontSee('09:00');
        $response->assertDontSee('18:00');
    }

    public function test_admin_can_navigate_to_attendance_detail(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
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
        $response->assertSee(
            'href="' . url('/attendance/' . $attendance->id) . '"',
            false
        );

        $detailResponse = $this->actingAs($admin)
            ->get('/admin/attendance/' . $attendance->id);

        $detailResponse->assertOk();
    }
}
