<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_their_attendance_list(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertOk();
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    public function test_attendance_list_displays_current_month(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendanceDate = now()->startOfMonth()->toDateString();

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => $attendanceDate,
            'clock_in' => '08:15:00',
            'clock_out' => '17:15:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertOk();
        $response->assertSee('08:15');
        $response->assertSee('17:15');
    }

    public function test_user_can_view_previous_month(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $previousMonthDate = now()
            ->subMonthNoOverflow()
            ->startOfMonth();

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => $previousMonthDate->toDateString(),
            'clock_in' => '07:21:00',
            'clock_out' => '16:21:00',
        ]);

        $previousMonth = $previousMonthDate->format('Y-m');

        $response = $this->actingAs($user)->get(
            '/attendance/list?date=' . $previousMonth
        );

        $response->assertOk();
        $response->assertSee('07:21');
        $response->assertSee('16:21');
    }

    public function test_user_can_view_next_month(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $nextMonthDate = now()
            ->addMonthNoOverflow()
            ->startOfMonth();

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => $nextMonthDate->toDateString(),
            'clock_in' => '08:32:00',
            'clock_out' => '17:32:00',
        ]);

        $nextMonth = $nextMonthDate->format('Y-m');

        $response = $this->actingAs($user)->get(
            '/attendance/list?date=' . $nextMonth
        );

        $response->assertOk();
        $response->assertSee('08:32');
        $response->assertSee('17:32');
    }

    public function test_user_cannot_view_another_users_attendance(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $anotherUser = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $anotherUser->id,
            'work_date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertOk();
        $response->assertDontSee('09:00');
        $response->assertDontSee('18:00');
    }
}
?>