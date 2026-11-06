<?php

namespace Tests\Feature\Api\V1;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRecordWriteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 認証済みユーザーが勤怠を登録できる
     */
    public function test_authenticated_user_can_create_attendance_record(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance-records', [
                'date' => '2026-10-01',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
                'comment' => '通常勤務',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.date', '2026-10-01')
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '18:00:00')
            ->assertJsonPath('data.comment', '通常勤務');

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '通常勤務',
        ]);
    }

    /**
     * 同じユーザーが同じ日付の勤怠を重複登録できない
     */
    public function test_user_cannot_create_duplicate_attendance_record(): void
    {
        $user = User::factory()->create();

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance-records', [
                'date' => '2026-10-01',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.date.0',
                'この日付の勤怠は既に登録されています。'
            );
    }

    /**
     * 必須項目が未入力の場合はエラーになる
     */
    public function test_attendance_registration_fails_when_required_fields_are_missing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance-records', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date',
                'clock_in',
            ])
            ->assertJsonPath(
                'errors.date.0',
                '勤怠日は必須です。'
            )
            ->assertJsonPath(
                'errors.clock_in.0',
                '出勤時刻は必須です。'
            );
    }

    /**
     * 勤怠日の形式が不正な場合はエラーになる
     */
    public function test_attendance_registration_fails_with_invalid_date_format(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance-records', [
                'date' => '2026/10/01',
                'clock_in' => '09:00:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.date.0',
                '勤怠日は YYYY-MM-DD 形式で指定してください。'
            );
    }

    /**
     * 出勤時刻の形式が不正な場合はエラーになる
     */
    public function test_attendance_registration_fails_with_invalid_clock_in_format(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance-records', [
                'date' => '2026-10-01',
                'clock_in' => '09:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.clock_in.0',
                '出勤時刻は HH:MM:SS 形式で指定してください。'
            );
    }

    /**
     * 退勤時刻が出勤時刻より前の場合はエラーになる
     */
    public function test_attendance_registration_fails_when_clock_out_is_before_clock_in(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance-records', [
                'date' => '2026-10-01',
                'clock_in' => '18:00:00',
                'clock_out' => '09:00:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.clock_out.0',
                '退勤時刻は出勤時刻より後の時刻を指定してください。'
            );
    }

    /**
     * 備考が255文字を超える場合はエラーになる
     */
    public function test_attendance_registration_fails_when_comment_exceeds_255_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance-records', [
                'date' => '2026-10-01',
                'clock_in' => '09:00:00',
                'comment' => str_repeat('あ', 256),
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.comment.0',
                '備考は 255 文字以内で入力してください。'
            );
    }

    /**
     * 自分の勤怠を更新できる
     */
    public function test_user_can_update_own_attendance_record(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/attendance-records/' . $attendance->id, [
                'date' => '2026-10-02',
                'clock_in' => '09:30:00',
                'clock_out' => '18:30:00',
                'comment' => '更新後の備考',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.date', '2026-10-02')
            ->assertJsonPath('data.clock_in', '09:30:00')
            ->assertJsonPath('data.clock_out', '18:30:00')
            ->assertJsonPath('data.comment', '更新後の備考');

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'work_date' => '2026-10-02',
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
            'comment' => '更新後の備考',
        ]);
    }

    /**
     * PATCHで勤怠の一部を更新できる
     */
    public function test_user_can_partially_update_attendance_record(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/attendance-records/' . $attendance->id, [
                'comment' => '備考のみ更新',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.comment', '備考のみ更新');

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'comment' => '備考のみ更新',
            'work_date' => '2026-10-01',
            'clock_in' => '09:00:00',
        ]);
    }

    /**
     * PATCHで出勤時刻だけを更新できる
     */
    public function test_user_can_partially_update_clock_in(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/attendance-records/' . $attendance->id, [
                'clock_in' => '10:00:00',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.clock_in', '10:00:00')
            ->assertJsonPath('data.clock_out', '18:00:00');

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '10:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    /**
     * PATCHで退勤時刻だけを更新できる
     */
    public function test_user_can_partially_update_clock_out(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/attendance-records/' . $attendance->id, [
                'clock_out' => '19:00:00',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '19:00:00');

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '19:00:00',
        ]);
    }

    /**
     * PATCHで出勤時刻と退勤時刻を同時に更新できる
     */
    public function test_user_can_partially_update_clock_in_and_clock_out(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/attendance-records/' . $attendance->id, [
                'clock_in' => '10:00:00',
                'clock_out' => '19:00:00',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.clock_in', '10:00:00')
            ->assertJsonPath('data.clock_out', '19:00:00');

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);
    }

    /**
     * PATCHで退勤時刻を既存の出勤時刻より前に変更するとエラーになる
     */
    public function test_user_cannot_update_clock_out_to_earlier_than_existing_clock_in(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/attendance-records/' . $attendance->id, [
                'clock_out' => '08:00:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.clock_out.0',
                '退勤時刻は出勤時刻より後の時刻を指定してください。'
            );

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    /**
     * PATCHで出勤時刻を退勤時刻より後に変更するとエラーになる
     */
    public function test_user_cannot_update_clock_in_to_later_than_existing_clock_out(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/attendance-records/' . $attendance->id, [
                'clock_in' => '19:00:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.clock_out.0',
                '退勤時刻は出勤時刻より後の時刻を指定してください。'
            );

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    /**
     * 他のユーザーの勤怠は更新できない
     */
    public function test_user_cannot_update_another_users_attendance_record(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/attendance-records/' . $attendance->id, [
                'date' => '2026-10-02',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);

        $response->assertForbidden()
            ->assertJsonPath(
                'error',
                'この操作を実行する権限がありません。'
            );
    }

    /**
     * 管理者は他のユーザーの勤怠も更新できる
     */
    public function test_admin_can_update_another_users_attendance_record(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $otherUser = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/v1/attendance-records/' . $attendance->id, [
                'date' => '2026-10-02',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'work_date' => '2026-10-02',
        ]);
    }

    /**
     * 自分の勤怠を削除できる
     */
    public function test_user_can_delete_own_attendance_record(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/attendance-records/' . $attendance->id);

        $response->assertNoContent();

        $this->assertDatabaseMissing('attendance_records', [
            'id' => $attendance->id,
        ]);
    }

    /**
     * 他のユーザーの勤怠は削除できない
     */
    public function test_user_cannot_delete_another_users_attendance_record(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/attendance-records/' . $attendance->id);

        $response->assertForbidden()
            ->assertExactJson([
                'error' => 'この操作を実行する権限がありません。',
            ]);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'user_id' => $otherUser->id,
        ]);
    }

    /**
     * 存在しない勤怠IDを更新すると404になる
     */
    public function test_updating_nonexistent_attendance_record_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/attendance-records/99999', [
                'date' => '2026-10-02',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);

        $response->assertNotFound()
            ->assertJsonPath(
                'error',
                '勤怠情報が見つかりませんでした。'
            );
    }

    /**
     * 存在しない勤怠IDを削除すると404になる
     */
    public function test_deleting_nonexistent_attendance_record_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/attendance-records/99999');

        $response->assertNotFound()
            ->assertJsonPath(
                'error',
                '勤怠情報が見つかりませんでした。'
            );
    }

    /**
     * 備考が255文字以内なら勤怠を登録できる
     */
    public function test_attendance_can_be_created_with_255_character_comment(): void
    {
        $user = User::factory()->create();
        $comment = str_repeat('あ', 255);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance-records', [
                'date' => '2026-10-01',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
                'comment' => $comment,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.comment', $comment);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
            'comment' => $comment,
        ]);
    }


    /**
     * 備考を255文字に更新できる
     */
    public function test_user_can_update_comment_with_255_characters(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
        ]);

        $comment = str_repeat('あ', 255);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/attendance-records/' . $attendance->id, [
                'comment' => $comment,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.comment', $comment);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'comment' => $comment,
        ]);
    }
}
