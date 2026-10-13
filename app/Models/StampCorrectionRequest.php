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
        'approval_status',
        'application_date',
    ];

    protected $casts = [
        'application_date' => 'date',
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