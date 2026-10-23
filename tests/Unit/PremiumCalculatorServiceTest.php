<?php

namespace Tests\Unit;

use App\Exceptions\PricingValidationException;
use App\Services\Pricing\PremiumCalculatorService;
use PHPUnit\Framework\TestCase;

class PremiumCalculatorServiceTest extends TestCase
{
    private PremiumCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PremiumCalculatorService();
    }

    public function test_matches_assignment_example_specification_exactly(): void
    {
        // Example from assignment:
        // DOB: 1998-04-10, destination: INTERNATIONAL, trip_start: 2026-11-10, trip_end: 2026-11-14, plan: PLUS, sum_insured: 300000
        // travel_days = 5, age multiplier = 1.00, premium = 20 * 5 * 1.00 * 1.80 * 1.25 = ₹225.00
        $result = $this->service->calculate(
            planCode: 'PLUS',
            destination: 'INTERNATIONAL',
            sumInsured: 300000,
            dateOfBirth: '1998-04-10',
            tripStart: '2026-11-10',
            tripEnd: '2026-11-14'
        );

        $this->assertEquals(5, $result->travelDays);
        $this->assertEquals(28, $result->ageAtTripStart);
        $this->assertEquals(20.00, $result->baseDailyRate);
        $this->assertEquals(1.00, $result->ageMultiplier);
        $this->assertEquals(1.80, $result->destinationMultiplier);
        $this->assertEquals(1.25, $result->coverageMultiplier);
        $this->assertEquals(225.00, $result->premiumAmount);
    }

    public function test_child_age_multiplier_0_to_17(): void
    {
        // Age 10: multiplier 1.10
        // BASIC (12) * 2 days * 1.10 * 1.00 (DOMESTIC) * 1.00 (100000) = 26.40
        $result = $this->service->calculate(
            planCode: 'BASIC',
            destination: 'DOMESTIC',
            sumInsured: 100000,
            dateOfBirth: '2016-01-01',
            tripStart: '2026-06-01',
            tripEnd: '2026-06-02'
        );

        $this->assertEquals(10, $result->ageAtTripStart);
        $this->assertEquals(1.10, $result->ageMultiplier);
        $this->assertEquals(26.40, $result->premiumAmount);
    }

    public function test_senior_age_multiplier_61_to_70(): void
    {
        // Age 65: multiplier 1.50
        // PLUS (20) * 1 day * 1.50 * 1.00 * 1.00 = 30.00
        $result = $this->service->calculate(
            planCode: 'PLUS',
            destination: 'DOMESTIC',
            sumInsured: 100000,
            dateOfBirth: '1961-01-01',
            tripStart: '2026-06-01',
            tripEnd: '2026-06-01'
        );

        $this->assertEquals(65, $result->ageAtTripStart);
        $this->assertEquals(1.50, $result->ageMultiplier);
        $this->assertEquals(30.00, $result->premiumAmount);
    }

    public function test_elder_age_multiplier_71_plus(): void
    {
        // Age 75: multiplier 2.00
        // PREMIUM (30) * 3 days * 2.00 * 1.80 (INTL) * 1.50 (500k) = 30 * 3 * 2 * 1.8 * 1.5 = 486.00
        $result = $this->service->calculate(
            planCode: 'PREMIUM',
            destination: 'INTERNATIONAL',
            sumInsured: 500000,
            dateOfBirth: '1951-01-01',
            tripStart: '2026-06-01',
            tripEnd: '2026-06-03'
        );

        $this->assertEquals(75, $result->ageAtTripStart);
        $this->assertEquals(2.00, $result->ageMultiplier);
        $this->assertEquals(486.00, $result->premiumAmount);
    }

    public function test_rejects_unsupported_plan_code(): void
    {
        $this->expectException(PricingValidationException::class);
        $this->expectExceptionMessage("Unsupported plan_code: 'GOLD'");

        $this->service->calculate(
            planCode: 'GOLD',
            destination: 'DOMESTIC',
            sumInsured: 100000,
            dateOfBirth: '1990-01-01',
            tripStart: '2026-06-01',
            tripEnd: '2026-06-05'
        );
    }

    public function test_rejects_unsupported_destination(): void
    {
        $this->expectException(PricingValidationException::class);
        $this->expectExceptionMessage("Unsupported destination: 'SPACE'");

        $this->service->calculate(
            planCode: 'BASIC',
            destination: 'SPACE',
            sumInsured: 100000,
            dateOfBirth: '1990-01-01',
            tripStart: '2026-06-01',
            tripEnd: '2026-06-05'
        );
    }

    public function test_rejects_unsupported_sum_insured(): void
    {
        $this->expectException(PricingValidationException::class);
        $this->expectExceptionMessage("Unsupported sum_insured: '250000'");

        $this->service->calculate(
            planCode: 'BASIC',
            destination: 'DOMESTIC',
            sumInsured: 250000,
            dateOfBirth: '1990-01-01',
            tripStart: '2026-06-01',
            tripEnd: '2026-06-05'
        );
    }

    public function test_rejects_invalid_trip_dates(): void
    {
        $this->expectException(PricingValidationException::class);
        $this->expectExceptionMessage('trip_end (2026-06-01) cannot be before trip_start (2026-06-05)');

        $this->service->calculate(
            planCode: 'BASIC',
            destination: 'DOMESTIC',
            sumInsured: 100000,
            dateOfBirth: '1990-01-01',
            tripStart: '2026-06-05',
            tripEnd: '2026-06-01'
        );
    }
}
