<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceClockInTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_clock_in(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
        ]);
    }

    public function test_user_cannot_clock_in_twice_in_one_day(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        $this->post('/attendance', [
            'action' => 'clock_in',
        ]);

        $this->assertDatabaseCount('attendances', 1);
    }
}
?>