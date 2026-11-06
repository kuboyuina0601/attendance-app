<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private function createAttendance(
        User $user,
        ?string $workDate = null
    ): Attendance {
        return Attendance::create([
            'user_id' => $user->id,
            'work_date' => $workDate ?? now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    public function test_attendance_detail_displays_logged_in_users_name(): void
    {
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)->get(
            '/attendance/'.$attendance->id
        );

        $response->assertOk();
        $response->assertSee('テストユーザー');
    }

    public function test_attendance_detail_displays_selected_date(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $workDate = now()->subDays(2)->toDateString();

        $attendance = $this->createAttendance($user, $workDate);

        $response = $this->actingAs($user)->get(
            '/attendance/'.$attendance->id
        );

        $response->assertOk();

        $expectedDate = Carbon::parse($workDate)
            ->format('n月j日');

        $response->assertSee($expectedDate);
    }

    public function test_attendance_detail_displays_clock_in_and_clock_out(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)->get(
            '/attendance/'.$attendance->id
        );

        $response->assertOk();
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    public function test_attendance_detail_displays_break_times(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->actingAs($user)->get(
            '/attendance/'.$attendance->id
        );

        $response->assertOk();
        $response->assertSee('12:00', false);
        $response->assertSee('13:00', false);
    }
}
