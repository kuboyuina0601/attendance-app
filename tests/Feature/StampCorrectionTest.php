<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StampCorrectionTest extends TestCase
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

    private function validCorrectionData(): array
    {
        return [
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '打刻時間の修正申請',
        ];
    }

    public function test_clock_in_after_clock_out_is_rejected(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $data = $this->validCorrectionData();
        $data['new_clock_in'] = '19:00';
        $data['new_clock_out'] = '18:00';

        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            $data
        );

        $response->assertSessionHasErrors([
            'new_clock_in' => '出勤時間が不適切な値です',
        ]);
    }

    public function test_break_in_after_clock_out_is_rejected(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $data = $this->validCorrectionData();
        $data['new_clock_out'] = '18:00';
        $data['new_break_in'] = ['18:30'];
        $data['new_break_out'] = ['18:45'];

        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            $data
        );

        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です',
        ]);
    }

    public function test_break_out_after_clock_out_is_rejected(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $data = $this->validCorrectionData();
        $data['new_clock_out'] = '18:00';
        $data['new_break_in'] = ['16:00'];
        $data['new_break_out'] = ['18:30'];

        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            $data
        );

        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    public function test_comment_is_required(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $data = $this->validCorrectionData();
        $data['comment'] = '';

        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            $data
        );

        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください',
        ]);
    }

    public function test_correction_request_is_displayed_on_admin_screens(): void
    {
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $data = $this->validCorrectionData();

        $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            $data
        );

        $application = StampCorrectionRequest::where(
            'attendance_id',
            $attendance->id
        )->firstOrFail();

        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/stamp_correction_request/list');

        $response->assertOk();
        $response->assertSee('打刻時間の修正申請');
        $response->assertSee('テストユーザー');
        $response->assertSee(
            '/stamp_correction_request/approve/' . $application->id,
            false
        );

        $detailResponse = $this->get(
            '/stamp_correction_request/approve/' . $application->id
        );

        $detailResponse->assertOk();
        $detailResponse->assertSee('テストユーザー');
        $detailResponse->assertSee('打刻時間の修正申請');
    }

    public function test_pending_correction_requests_are_displayed_on_user_list(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance1 = $this->createAttendance(
            $user,
            now()->subDay()->toDateString()
        );

        $attendance2 = $this->createAttendance(
            $user,
            now()->toDateString()
        );

        $data1 = $this->validCorrectionData();
        $data1['comment'] = '1件目の修正申請';

        $data2 = $this->validCorrectionData();
        $data2['comment'] = '2件目の修正申請';

        $this->actingAs($user)->post(
            '/attendance/' . $attendance1->id,
            $data1
        );

        $this->post(
            '/attendance/' . $attendance2->id,
            $data2
        );

        $response = $this->get('/stamp_correction_request/list');

        $response->assertOk();
        $response->assertSee('承認待ち');
        $response->assertSee('1件目の修正申請');
        $response->assertSee('2件目の修正申請');
    }

    public function test_approved_correction_requests_are_displayed_on_user_list(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $data = $this->validCorrectionData();

        $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            $data
        );

        $application = StampCorrectionRequest::where(
            'attendance_id',
            $attendance->id
        )->firstOrFail();

        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $this->actingAs($admin)->post(
            '/stamp_correction_request/approve/' . $application->id
        );

        $response = $this->actingAs($user)->get(
            '/stamp_correction_request/list'
        );

        $response->assertOk();
        $response->assertSee('承認済み');
        $response->assertSee('打刻時間の修正申請');

        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $application->id,
            'approval_status' => '承認済み',
        ]);
    }

    public function test_user_can_navigate_to_correction_request_detail(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            $this->validCorrectionData()
        );

        $application = StampCorrectionRequest::where(
            'attendance_id',
            $attendance->id
        )->firstOrFail();

        $response = $this->get('/stamp_correction_request/list');

        $response->assertOk();
        $response->assertSee(
            '/application/' . $application->id,
            false
        );

        $detailResponse = $this->get(
            '/application/' . $application->id
        );

        $detailResponse->assertOk();
    }
}
?>