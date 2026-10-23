<?php

namespace App\Services\Pricing;

class PricingResult
{
    public function __construct(
        public readonly int $travelDays,
        public readonly int $ageAtTripStart,
        public readonly float $baseDailyRate,
        public readonly float $ageMultiplier,
        public readonly float $destinationMultiplier,
        public readonly float $coverageMultiplier,
        public readonly float $premiumAmount,
        public readonly array $breakdown
    ) {}

    public function toArray(): array
    {
        return [
            'travel_days' => $this->travelDays,
            'age_at_trip_start' => $this->ageAtTripStart,
            'base_daily_rate' => $this->baseDailyRate,
            'age_multiplier' => $this->ageMultiplier,
            'destination_multiplier' => $this->destinationMultiplier,
            'coverage_multiplier' => $this->coverageMultiplier,
            'premium_amount' => $this->premiumAmount,
            'pricing_breakdown' => $this->breakdown,
        ];
    }
}
