<?php

namespace Tests\Feature\Api\V1;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRecordReadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 認証なしで勤怠一覧を取得できる
     */
    public function test_anyone_can_get_attendance_records(): void
    {
        $attendance = Attendance::factory()->create();

        $response = $this->getJson('/api/v1/attendance-records');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $attendance->id)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'user',
                        'user_name',
                        'date',
                        'clock_in',
                        'clock_out',
                        'total_time',
                        'total_break_time',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    /**
     * user_idで勤怠一覧を絞り込める
     */
    public function test_attendance_records_can_be_filtered_by_user_id(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
        ]);

        Attendance::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson(
            '/api/v1/attendance-records?user_id='.$user->id
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $attendance->id);
    }

    /**
     * dateで勤怠一覧を絞り込める
     */
    public function test_attendance_records_can_be_filtered_by_date(): void
    {
        $attendance = Attendance::factory()->create([
            'work_date' => '2026-10-01',
        ]);

        Attendance::factory()->create([
            'work_date' => '2026-10-02',
        ]);

        $response = $this->getJson(
            '/api/v1/attendance-records?date=2026-10-01'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $attendance->id);
    }

    /**
     * monthで勤怠一覧を絞り込める
     */
    public function test_attendance_records_can_be_filtered_by_month(): void
    {
        $attendance = Attendance::factory()->create([
            'work_date' => '2026-10-15',
        ]);

        Attendance::factory()->create([
            'work_date' => '2026-11-01',
        ]);

        $response = $this->getJson(
            '/api/v1/attendance-records?month=2026-10'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $attendance->id);
    }

    /**
     * per_pageでページネーションできる
     */
    public function test_attendance_records_can_be_paginated(): void
    {
        Attendance::factory()->count(5)->create();

        $response = $this->getJson(
            '/api/v1/attendance-records?per_page=2'
        );

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);
    }

    /**
     * 認証なしで勤怠詳細を取得できる
     */
    public function test_anyone_can_get_attendance_record_details(): void
    {
        $attendance = Attendance::factory()->create([
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        AttendanceBreak::factory()->create([
            'attendance_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        StampCorrectionRequest::factory()->create([
            'attendance_id' => $attendance->id,
        ]);

        $response = $this->getJson(
            '/api/v1/attendance-records/'.$attendance->id
        );

        $response->assertOk()
            ->assertJsonPath('data.id', $attendance->id)
            ->assertJsonPath('data.user.id', $attendance->user_id)
            ->assertJsonPath('data.total_time', '08:00')
            ->assertJsonPath('data.total_break_time', '01:00')
            ->assertJsonCount(1, 'data.breaks')
            ->assertJsonCount(1, 'data.applications');
    }

    /**
     * 存在しない勤怠IDを指定すると404とエラーメッセージを返す
     */
    public function test_attendance_record_not_found_returns_404(): void
    {
        $response = $this->getJson(
            '/api/v1/attendance-records/99999'
        );

        $response->assertNotFound()
            ->assertJsonPath(
                'error',
                '勤怠情報が見つかりませんでした。'
            );
    }
}
