<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'new_clock_in' => $this->new_clock_in,
            'new_clock_out' => $this->new_clock_out,
            'comment' => $this->comment,
            'application_date' => $this->application_date?->format('Y-m-d'),
            'approval_status' => $this->approval_status,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
        ];
    }
}