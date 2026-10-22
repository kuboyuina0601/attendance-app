<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stamp_correction_requests', function (Blueprint $table) {
            $table->id();
            //どの勤怠か
            $table->foreignId('attendance_id')->constrained('attendances');
            //修正希望の勤怠情報
            $table->time('new_clock_in');
            $table->time('new_clock_out');
            $table->string('comment', 255);
            $table->date('application_date');
            //承認状況（承認待ち、承認済み）
            $table->enum('approval_status', ['承認待ち', '承認済み'])->default('承認待ち');
            $table->datetime('approved_at')->nullable();
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