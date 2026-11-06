<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance_records';

    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    protected $casts = [
        'work_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceBreaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    public function stampCorrectionRequests(): HasMany
    {
        return $this->hasMany(StampCorrectionRequest::class);
    }
}
