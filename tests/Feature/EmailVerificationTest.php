<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 会員登録後に認証メールが送信されることを確認する
     */
    public function test_verification_email_is_sent_after_registration(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();

        Notification::assertSentTo(
            $user,
            VerifyEmail::class
        );

        $this->assertNull($user->email_verified_at);
    }

    /**
     * メール認証誘導画面からメール認証サイトへ遷移できることを確認する
     */
    public function test_verification_notice_has_link_to_mailpit(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertOk();

        $response->assertSee(
            'href="http://localhost:8025"',
            false
        );

        $response->assertSee('認証はこちらから');
    }

    /**
     * メール認証完了後に勤怠登録画面へ遷移することを確認する
     */
    public function test_user_is_redirected_to_attendance_after_email_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect('/attendance?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}