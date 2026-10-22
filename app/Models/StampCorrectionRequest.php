<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StampCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'new_clock_in',
        'new_clock_out',
        'comment',
        'application_date',
        'approval_status',
        'approved_at',
    ];

    protected $casts = [
        'application_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function stampCorrectionRequestBreaks()
    {
        return $this->hasMany(StampCorrectionRequestBreak::class);
    }
}