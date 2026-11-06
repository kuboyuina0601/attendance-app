<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampCorrectionRequestBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'stamp_correction_request_id',
        'new_break_in',
        'new_break_out',
    ];

    public function stampCorrectionRequest(): BelongsTo
    {
        return $this->belongsTo(StampCorrectionRequest::class);
    }
}
