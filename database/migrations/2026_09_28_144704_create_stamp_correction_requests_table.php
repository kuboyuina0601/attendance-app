<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stamp_correction_requests', function (Blueprint $table) {
            $table->id();
            //誰のどの勤怠か
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('attendance_id')->constrained('attendances');
            //修正希望の勤怠情報
            $table->date('new_date');
            $table->time('new_clock_in')->nullable();
            $table->time('new_clock_out')->nullable();
            $table->text('comment');
            //承認状況（承認待ち、承認済み）
            $table->string('approval_status', 4)->default('承認待ち');
            $table->timestamp('application_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_correction_requests');
    }
};