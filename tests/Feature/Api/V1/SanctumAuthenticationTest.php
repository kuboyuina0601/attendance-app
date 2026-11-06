<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 正しいログイン情報でAPIトークンを発行できる
     */
    public function test_user_can_login_and_receive_api_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'ログインしました。')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', $user->name)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'attendance-api',
        ]);
    }

    /**
     * メールアドレスが未入力の場合はエラーになる
     */
    public function test_api_login_fails_when_email_is_missing(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.email.0',
                'メールアドレスは必須です。'
            );
    }

    /**
     * メールアドレスの形式が不正な場合はエラーになる
     */
    public function test_api_login_fails_with_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'invalid-email',
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.email.0',
                '正しいメールアドレスを入力してください'
            );
    }

    /**
     * パスワードが未入力の場合はエラーになる
     */
    public function test_api_login_fails_when_password_is_missing(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.password.0',
                'パスワードは必須です。'
            );
    }

    /**
     * メールアドレスが登録されていない場合はログインできない
     */
    public function test_api_login_fails_with_unregistered_email(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'unknown@example.com',
            'password' => 'password',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath(
                'message',
                'メールアドレスまたはパスワードが正しくありません。'
            );
    }

    /**
     * パスワードが間違っている場合はログインできない
     */
    public function test_api_login_fails_with_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath(
                'message',
                'メールアドレスまたはパスワードが正しくありません。'
            );
    }

    /**
     * 発行されたトークンで勤怠を登録できる
     */
    public function test_user_can_access_protected_api_with_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $loginResponse->assertOk();

        $token = $loginResponse->json('token');

        $response = $this->withToken($token)
            ->postJson('/api/v1/attendance-records', [
                'date' => '2026-10-01',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'work_date' => '2026-10-01',
        ]);
    }

    /**
     * トークンなしでは認証必須APIにアクセスできない
     */
    public function test_user_cannot_access_protected_api_without_token(): void
    {
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath(
                'message',
                'Unauthenticated.'
            );
    }

    /**
     * 無効なトークンでは認証必須APIにアクセスできない
     */
    public function test_user_cannot_access_protected_api_with_invalid_token(): void
    {
        $response = $this->withToken('invalid-token')
            ->postJson('/api/v1/attendance-records', [
                'date' => '2026-10-01',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);

        $response->assertUnauthorized()
            ->assertJsonPath(
                'message',
                'Unauthenticated.'
            );
    }

    /**
     * トークンなしでは勤怠を更新できない
     */
    public function test_user_cannot_update_attendance_without_token(): void
    {
        $response = $this->putJson('/api/v1/attendance-records/1', [
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    /**
     * トークンなしでは勤怠を部分更新できない
     */
    public function test_user_cannot_partially_update_attendance_without_token(): void
    {
        $response = $this->patchJson('/api/v1/attendance-records/1', [
            'clock_in' => '09:00:00',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    /**
     * トークンなしでは勤怠を削除できない
     */
    public function test_user_cannot_delete_attendance_without_token(): void
    {
        $response = $this->deleteJson('/api/v1/attendance-records/1');

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }
}
