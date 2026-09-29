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
        Schema::create('stamp_correction_request_breaks', function (Blueprint $table) {
            $table->id();

            //どの修正申請の休憩か
            $table->foreignId('stamp_correction_request_id')
            ->constrained(
                'stamp_correction_requests',
                'id',
                'scrb_request_id_fk'
            );
            
            //修正希望の休憩時間
            $table->time('break_start');
            $table->time('break_end');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_correction_request_breaks');
    }
};