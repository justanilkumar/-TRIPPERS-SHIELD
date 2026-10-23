<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuanceAttempt extends Model
{
    use HasFactory;

    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_TIMED_OUT = 'TIMED_OUT';
    public const STATUS_PENDING = 'PENDING';

    protected $guarded = [];

    protected $casts = [
        'attempt_number' => 'integer',
        'http_status' => 'integer',
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function policyRequest(): BelongsTo
    {
        return $this->belongsTo(PolicyRequest::class);
    }
}
