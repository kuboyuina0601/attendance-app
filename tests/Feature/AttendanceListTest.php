<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
            'work_date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-02',
            'clock_in' => '08:00:00',
            'clock_out' => '17:00:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));

        try {
            $response = $this->actingAs($user)->get('/attendance/list');

            $response->assertOk();
            $response->assertSee('09:00');
            $response->assertSee('18:00');
            $response->assertSee('08:00');
            $response->assertSee('17:00');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_attendance_list_displays_current_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));

        try {
            $user = User::factory()->create([
                'admin_status' => false,
            ]);

            $response = $this->actingAs($user)->get('/attendance/list');

            $response->assertOk();
            $response->assertSee('2026/10');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_user_can_view_previous_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));

        try {
            $user = User::factory()->create([
                'admin_status' => false,
            ]);

            Attendance::create([
                'user_id' => $user->id,
                'work_date' => '2026-09-01',
                'clock_in' => '07:21:00',
                'clock_out' => '16:21:00',
            ]);

            $response = $this->actingAs($user)->get(
                '/attendance/list?date=2026-09'
            );

            $response->assertOk();
            $response->assertSee('2026/09');
            $response->assertSee('07:21');
            $response->assertSee('16:21');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_user_can_view_next_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));

        try {
            $user = User::factory()->create([
                'admin_status' => false,
            ]);

            Attendance::create([
                'user_id' => $user->id,
                'work_date' => '2026-11-01',
                'clock_in' => '08:32:00',
                'clock_out' => '17:32:00',
            ]);

            $response = $this->actingAs($user)->get(
                '/attendance/list?date=2026-11'
            );

            $response->assertOk();
            $response->assertSee('2026/11');
            $response->assertSee('08:32');
            $response->assertSee('17:32');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_user_can_navigate_to_attendance_detail(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->actingAs($user);

        $response = $this->get('/attendance/list');

        $response->assertOk();
        $response->assertSee('/attendance/'.$attendance->id, false);

        $detailResponse = $this->get('/attendance/'.$attendance->id);

        $detailResponse->assertOk();
    }
}
