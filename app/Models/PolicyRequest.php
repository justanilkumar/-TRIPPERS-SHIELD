<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicyRequest extends Model
{
    use HasFactory;

    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_VALIDATED = 'VALIDATED';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_PENDING_CONFIRMATION = 'PENDING_CONFIRMATION';
    public const STATUS_ISSUED = 'ISSUED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_NEEDS_RECONCILIATION = 'NEEDS_RECONCILIATION';

    public const VALID_STATUSES = [
        self::STATUS_RECEIVED,
        self::STATUS_VALIDATED,
        self::STATUS_SUBMITTED,
        self::STATUS_PENDING_CONFIRMATION,
        self::STATUS_ISSUED,
        self::STATUS_FAILED,
        self::STATUS_NEEDS_RECONCILIATION,
    ];

    public const PLAN_BASIC = 'BASIC';
    public const PLAN_PLUS = 'PLUS';
    public const PLAN_PREMIUM = 'PREMIUM';

    public const SUPPORTED_PLANS = [
        self::PLAN_BASIC,
        self::PLAN_PLUS,
        self::PLAN_PREMIUM,
    ];

    public const DESTINATION_DOMESTIC = 'DOMESTIC';
    public const DESTINATION_INTERNATIONAL = 'INTERNATIONAL';

    public const SUPPORTED_DESTINATIONS = [
        self::DESTINATION_DOMESTIC,
        self::DESTINATION_INTERNATIONAL,
    ];

    public const SUPPORTED_SUMS_INSURED = [
        100000,
        300000,
        500000,
    ];

    protected $guarded = [];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'trip_start' => 'date:Y-m-d',
        'trip_end' => 'date:Y-m-d',
        'travel_days' => 'integer',
        'age_at_trip_start' => 'integer',
        'attempt_count' => 'integer',
        'sum_insured' => 'decimal:2',
        'base_daily_rate' => 'decimal:2',
        'age_multiplier' => 'decimal:2',
        'destination_multiplier' => 'decimal:2',
        'coverage_multiplier' => 'decimal:2',
        'premium_amount' => 'decimal:2',
        'pricing_breakdown' => 'array',
        'issued_at' => 'datetime',
    ];

    public function attempts(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class)->orderBy('attempt_number', 'asc');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProviderEvent::class)->orderBy('id', 'asc');
    }

    public function stateHistory(): HasMany
    {
        return $this->hasMany(PolicyStateHistory::class)->orderBy('id', 'asc');
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function canBeSubmitted(): bool
    {
        return in_array($this->status, [
            self::STATUS_RECEIVED,
            self::STATUS_VALIDATED,
            self::STATUS_FAILED,
            self::STATUS_NEEDS_RECONCILIATION,
        ], true);
    }
}
