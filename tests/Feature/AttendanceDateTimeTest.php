<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceDateTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_date_and_time_are_displayed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 10:30:00'));

        try {
            $user = User::factory()->create([
                'admin_status' => false,
            ]);

            $response = $this->actingAs($user)->get('/attendance');

            $response->assertStatus(200);
            $response->assertSee('2026');
            $response->assertSee('10');
            $response->assertSee('28');
            $response->assertSee('10:30');
        } finally {
            Carbon::setTestNow();
        }
    }
}
?>