<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 未ログインユーザーはログイン画面にリダイレクトされる
     */
    public function test_guest_is_redirected_to_login_page(): void
    {
        $response = $this->get('/attendance/report');

        $response->assertRedirect('/login');
    }

    /**
     * ログインユーザーはマイ勤怠レポートを表示できる
     */
    public function test_authenticated_user_can_view_attendance_report(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/attendance/report');

        $response->assertOk()
            ->assertViewIs('reports.index')
            ->assertViewHas([
                'summary',
                'monthlyTrend',
                'anomalies',
            ]);
    }

    /**
     * 自分の勤怠だけが集計される
     */
    public function test_attendance_report_only_uses_authenticated_users_records(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->startOfDay());

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        AttendanceBreak::factory()->create([
            'attendance_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        Attendance::factory()->create([
            'user_id' => $otherUser->id,
            'work_date' => '2026-10-02',
            'clock_in' => '09:00:00',
            'clock_out' => '20:00:00',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/report');

        $response->assertOk()
            ->assertViewHas('summary', function ($summary) {
                return $summary['total_work_minutes'] === 480;
            });
    }

    /**
     * 総労働時間・総残業時間・平均労働時間が正しく計算される
     */
    public function test_attendance_report_calculates_working_time_correctly(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->startOfDay());

        $user = User::factory()->create();

        $firstAttendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '19:00:00',
        ]);

        AttendanceBreak::factory()->create([
            'attendance_id' => $firstAttendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $secondAttendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-09-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        AttendanceBreak::factory()->create([
            'attendance_id' => $secondAttendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/report');

        $response->assertOk()
            ->assertViewHas('summary', function ($summary) {
                return $summary['total_work_minutes'] === 1020
                    && $summary['total_overtime_minutes'] === 60
                    && $summary['avg_work_minutes'] === 510;
            });
    }

    /**
     * 過去6か月分の月次推移が表示される
     */
    public function test_attendance_report_displays_six_months_trend(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->startOfDay());

        $user = User::factory()->create();

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/report');

        $response->assertOk()
            ->assertViewHas('monthlyTrend', function ($monthlyTrend) {
                return count($monthlyTrend) === 6
                    && $monthlyTrend[0]['month'] === '2026/05'
                    && $monthlyTrend[5]['month'] === '2026/10'
                    && $monthlyTrend[0]['work_minutes'] === 0
                    && $monthlyTrend[5]['work_minutes'] === 540;
            });
    }

    /**
     * 遅刻・早退・長時間労働が正しく検知される
     */
    public function test_attendance_report_detects_anomalies(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->startOfDay());

        $user = User::factory()->create();

        // 遅刻・早退
        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
            'clock_in' => '09:30:00',
            'clock_out' => '17:30:00',
        ]);

        // 10時間を超える労働
        $longAttendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-02',
            'clock_in' => '08:00:00',
            'clock_out' => '19:00:00',
        ]);

        AttendanceBreak::factory()->create([
            'attendance_id' => $longAttendance->id,
            'break_in' => '12:00:00',
            'break_out' => '12:30:00',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/report');

        $response->assertOk()
            ->assertViewHas('anomalies', function ($anomalies) {
                return $anomalies['late_count'] === 1
                    && $anomalies['early_leave_count'] === 1
                    && $anomalies['long_work_count'] === 1;
            });
    }

    /**
     * 勤怠データがない場合も正常に表示される
     */
    public function test_attendance_report_displays_zero_values_without_records(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->startOfDay());

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/attendance/report');

        $response->assertOk()
            ->assertViewHas('summary', function ($summary) {
                return $summary['total_work_minutes'] === 0
                    && $summary['total_overtime_minutes'] === 0
                    && $summary['avg_work_minutes'] === 0;
            })
            ->assertViewHas('monthlyTrend', function ($monthlyTrend) {
                return count($monthlyTrend) === 6
                    && collect($monthlyTrend)->every(
                        fn($row) => $row['work_minutes'] === 0
                            && $row['overtime_minutes'] === 0
                    );
            })
            ->assertViewHas('anomalies', function ($anomalies) {
                return $anomalies['late_count'] === 0
                    && $anomalies['early_leave_count'] === 0
                    && $anomalies['long_work_count'] === 0;
            });
    }
}
