<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_users_attendance_for_the_selected_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00'));

        try {
            $admin = User::factory()->create([
                'admin_status' => true,
            ]);

            $user1 = User::factory()->create([
                'name' => 'テストユーザーA',
                'admin_status' => false,
            ]);

            $user2 = User::factory()->create([
                'name' => 'テストユーザーB',
                'admin_status' => false,
            ]);

            Attendance::factory()->create([
                'user_id' => $user1->id,
                'work_date' => '2026-10-28',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);

            Attendance::factory()->create([
                'user_id' => $user2->id,
                'work_date' => '2026-10-28',
                'clock_in' => '10:00:00',
                'clock_out' => '19:00:00',
            ]);

            $response = $this->actingAs($admin)
                ->get('/admin/attendance/list');

            $response->assertOk();
            $response->assertSee('テストユーザーA');
            $response->assertSee('テストユーザーB');
            $response->assertSee('09:00');
            $response->assertSee('18:00');
            $response->assertSee('10:00');
            $response->assertSee('19:00');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_attendance_list_displays_current_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00'));

        try {
            $admin = User::factory()->create([
                'admin_status' => true,
            ]);

            $response = $this->actingAs($admin)
                ->get('/admin/attendance/list');

            $response->assertOk();
            $response->assertSee('2026/10/28');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_can_view_previous_day_attendance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00'));

        try {
            $admin = User::factory()->create([
                'admin_status' => true,
            ]);

            $user = User::factory()->create([
                'name' => '前日確認ユーザー',
                'admin_status' => false,
            ]);

            Attendance::factory()->create([
                'user_id' => $user->id,
                'work_date' => '2026-10-27',
                'clock_in' => '07:00:00',
                'clock_out' => '16:00:00',
            ]);

            $response = $this->actingAs($admin)
                ->get('/admin/attendance/list?date=2026-10-27');

            $response->assertOk();
            $response->assertSee('2026/10/27');
            $response->assertSee('前日確認ユーザー');
            $response->assertSee('07:00');
            $response->assertSee('16:00');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_can_view_next_day_attendance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00'));

        try {
            $admin = User::factory()->create([
                'admin_status' => true,
            ]);

            $user = User::factory()->create([
                'name' => '翌日確認ユーザー',
                'admin_status' => false,
            ]);

            Attendance::factory()->create([
                'user_id' => $user->id,
                'work_date' => '2026-10-29',
                'clock_in' => '11:00:00',
                'clock_out' => '20:00:00',
            ]);

            $response = $this->actingAs($admin)
                ->get('/admin/attendance/list?date=2026-10-29');

            $response->assertOk();
            $response->assertSee('2026/10/29');
            $response->assertSee('翌日確認ユーザー');
            $response->assertSee('11:00');
            $response->assertSee('20:00');
        } finally {
            Carbon::setTestNow();
        }
    }
}
?>