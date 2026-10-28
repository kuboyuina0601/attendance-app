<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者が当日の一般ユーザーの勤怠一覧を確認できる。
     */
    public function test_admin_can_view_all_users_attendance_for_the_selected_day(): void
    {
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

        $date = now()->format('Y-m-d');

        Attendance::factory()->create([
            'user_id' => $user1->id,
            'work_date' => $date,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        Attendance::factory()->create([
            'user_id' => $user2->id,
            'work_date' => $date,
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/list?date=' . $date);

        $response->assertOk();
        $response->assertSee('テストユーザーA');
        $response->assertSee('テストユーザーB');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('10:00');
        $response->assertSee('19:00');
    }

    /**
     * 管理者が選択した日付の勤怠一覧を確認できる。
     */
    public function test_admin_can_view_attendance_for_selected_date(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '日付確認ユーザー',
            'admin_status' => false,
        ]);

        $date = now()->subDay()->format('Y-m-d');
        $otherDate = now()->format('Y-m-d');

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $date,
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $otherDate,
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/list?date=' . $date);

        $response->assertOk();
        $response->assertSee('08:30');
        $response->assertSee('17:30');
        $response->assertDontSee('09:30');
        $response->assertDontSee('18:30');
    }

    /**
     * 管理者が前日・翌日の日付を指定して一覧を表示できる。
     */
    public function test_admin_can_navigate_to_previous_and_next_day(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '日付移動ユーザー',
            'admin_status' => false,
        ]);

        $today = now()->startOfDay();
        $previousDay = $today->copy()->subDay();
        $nextDay = $today->copy()->addDay();

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $previousDay->format('Y-m-d'),
            'clock_in' => '07:00:00',
            'clock_out' => '16:00:00',
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $nextDay->format('Y-m-d'),
            'clock_in' => '11:00:00',
            'clock_out' => '20:00:00',
        ]);

        $previousResponse = $this->actingAs($admin)
            ->get('/admin/attendance/list?date=' . $previousDay->format('Y-m-d'));

        $previousResponse->assertOk();
        $previousResponse->assertSee('07:00');
        $previousResponse->assertSee('16:00');

        $nextResponse = $this->actingAs($admin)
            ->get('/admin/attendance/list?date=' . $nextDay->format('Y-m-d'));

        $nextResponse->assertOk();
        $nextResponse->assertSee('11:00');
        $nextResponse->assertSee('20:00');
    }

    /**
     * 一般ユーザーの勤怠一覧には管理者の勤怠を含めない。
     */
    public function test_admin_attendance_is_not_included_in_the_attendance_list(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
            'name' => '管理者ユーザー',
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
            'name' => '一般ユーザー',
        ]);

        $date = now()->format('Y-m-d');

        Attendance::factory()->create([
            'user_id' => $admin->id,
            'work_date' => $date,
            'clock_in' => '08:00:00',
            'clock_out' => '17:00:00',
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $date,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/list?date=' . $date);

        $response->assertOk();
        $response->assertSee('一般ユーザー');
        $response->assertDontSee('管理者ユーザー');
    }
}
