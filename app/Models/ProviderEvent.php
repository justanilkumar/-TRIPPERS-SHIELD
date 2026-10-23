<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderEvent extends Model
{
    use HasFactory;

    public const EVENT_POLICY_ISSUED = 'POLICY_ISSUED';
    public const EVENT_POLICY_FAILED = 'POLICY_FAILED';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
    ];

    public function policyRequest(): BelongsTo
    {
        return $this->belongsTo(PolicyRequest::class);
    }
}
