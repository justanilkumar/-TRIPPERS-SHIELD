<?php

namespace App\Services\Pricing;

use App\Exceptions\PricingValidationException;
use Carbon\Carbon;

class PremiumCalculatorService
{
    private const BASE_RATES = [
        'BASIC' => '12.00',
        'PLUS' => '20.00',
        'PREMIUM' => '30.00',
    ];

    private const DESTINATION_MULTIPLIERS = [
        'DOMESTIC' => '1.00',
        'INTERNATIONAL' => '1.80',
    ];

    private const COVERAGE_MULTIPLIERS = [
        '100000' => '1.00',
        '300000' => '1.25',
        '500000' => '1.50',
    ];

    /**
     * Calculate premium deterministically according to business rules.
     *
     * @throws PricingValidationException
     */
    public function calculate(
        string $planCode,
        string $destination,
        float|int|string $sumInsured,
        string $dateOfBirth,
        string $tripStart,
        string $tripEnd
    ): PricingResult {
        $normalizedPlan = strtoupper(trim($planCode));
        if (!isset(self::BASE_RATES[$normalizedPlan])) {
            throw new PricingValidationException("Unsupported plan_code: '{$planCode}'. Supported plans: BASIC, PLUS, PREMIUM.");
        }
        $baseRateStr = self::BASE_RATES[$normalizedPlan];

        $normalizedDestination = strtoupper(trim($destination));
        if (!isset(self::DESTINATION_MULTIPLIERS[$normalizedDestination])) {
            throw new PricingValidationException("Unsupported destination: '{$destination}'. Supported destinations: DOMESTIC, INTERNATIONAL.");
        }
        $destMultiplierStr = self::DESTINATION_MULTIPLIERS[$normalizedDestination];

        $normalizedSum = (string) (int) $sumInsured;
        if (!isset(self::COVERAGE_MULTIPLIERS[$normalizedSum])) {
            throw new PricingValidationException("Unsupported sum_insured: '{$sumInsured}'. Supported values: 100000, 300000, 500000.");
        }
        $coverageMultiplierStr = self::COVERAGE_MULTIPLIERS[$normalizedSum];

        $dob = Carbon::parse($dateOfBirth)->startOfDay();
        $start = Carbon::parse($tripStart)->startOfDay();
        $end = Carbon::parse($tripEnd)->startOfDay();

        if ($end->lt($start)) {
            throw new PricingValidationException("trip_end ({$tripEnd}) cannot be before trip_start ({$tripStart}).");
        }

        if ($start->lt($dob)) {
            throw new PricingValidationException("trip_start cannot be before date_of_birth.");
        }

        // Travel days inclusive
        $travelDays = (int) $start->diffInDays($end) + 1;
        if ($travelDays < 1) {
            $travelDays = 1;
        }

        // Age calculated on trip start
        $ageAtTripStart = (int) $dob->diffInYears($start);

        $ageMultiplierStr = $this->getAgeMultiplier($ageAtTripStart);

        // Decimal-safe arithmetic using BCMath:
        // formula: base_daily_rate * travel_days * age_multiplier * destination_multiplier * coverage_multiplier
        $step1 = bcmul($baseRateStr, (string) $travelDays, 6);
        $step2 = bcmul($step1, $ageMultiplierStr, 6);
        $step3 = bcmul($step2, $destMultiplierStr, 6);
        $step4 = bcmul($step3, $coverageMultiplierStr, 6);

        // Round final premium to 2 decimal places using half-up decimal rounding
        $finalPremium = round((float) $step4, 2);

        $breakdown = [
            'plan_code' => $normalizedPlan,
            'base_daily_rate' => (float) $baseRateStr,
            'travel_days' => $travelDays,
            'trip_start' => $start->toDateString(),
            'trip_end' => $end->toDateString(),
            'date_of_birth' => $dob->toDateString(),
            'age_at_trip_start' => $ageAtTripStart,
            'age_multiplier' => (float) $ageMultiplierStr,
            'destination' => $normalizedDestination,
            'destination_multiplier' => (float) $destMultiplierStr,
            'sum_insured' => (float) $normalizedSum,
            'coverage_multiplier' => (float) $coverageMultiplierStr,
            'calculated_premium' => $finalPremium,
            'formula' => 'base_daily_rate * travel_days * age_multiplier * destination_multiplier * coverage_multiplier',
        ];

        return new PricingResult(
            travelDays: $travelDays,
            ageAtTripStart: $ageAtTripStart,
            baseDailyRate: (float) $baseRateStr,
            ageMultiplier: (float) $ageMultiplierStr,
            destinationMultiplier: (float) $destMultiplierStr,
            coverageMultiplier: (float) $coverageMultiplierStr,
            premiumAmount: $finalPremium,
            breakdown: $breakdown
        );
    }

    /**
     * Age multiplier on trip start:
     * 0-17: 1.10
     * 18-60: 1.00
     * 61-70: 1.50
     * 71+: 2.00
     */
    private function getAgeMultiplier(int $age): string
    {
        if ($age <= 17) {
            return '1.10';
        }

        if ($age <= 60) {
            return '1.00';
        }

        if ($age <= 70) {
            return '1.50';
        }

        return '2.00';
    }
}
