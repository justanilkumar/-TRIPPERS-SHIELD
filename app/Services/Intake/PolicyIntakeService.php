<?php

namespace App\Services\Intake;

use App\Exceptions\PricingValidationException;
use App\Models\PolicyRequest;
use App\Services\Pricing\PremiumCalculatorService;
use App\Services\StateMachine\PolicyStateMachine;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class PolicyIntakeService
{
    public function __construct(
        protected PremiumCalculatorService $calculator,
        protected PolicyStateMachine $stateMachine
    ) {}

    /**
     * Intake a policy request idempotently.
     * Returns ['request' => PolicyRequest, 'is_new' => bool]
     *
     * @throws PricingValidationException
     */
    public function intake(array $data): array
    {
        $agentId = trim((string) ($data['agent_id'] ?? ''));
        $clientRequestId = trim((string) ($data['client_request_id'] ?? ''));

        // Quick check for existing record
        $existing = PolicyRequest::query()
            ->where('agent_id', $agentId)
            ->where('client_request_id', $clientRequestId)
            ->first();

        if ($existing) {
            return [
                'request' => $existing,
                'is_new' => false,
            ];
        }

        // Calculate deterministic premium and snapshot factors
        $pricing = $this->calculator->calculate(
            planCode: $data['plan_code'],
            destination: $data['destination'],
            sumInsured: $data['sum_insured'],
            dateOfBirth: $data['date_of_birth'],
            tripStart: $data['trip_start'],
            tripEnd: $data['trip_end']
        );

        try {
            return DB::transaction(function () use ($data, $agentId, $clientRequestId, $pricing) {
                // Secondary check inside transaction lock
                $lockedExisting = PolicyRequest::query()
                    ->where('agent_id', $agentId)
                    ->where('client_request_id', $clientRequestId)
                    ->lockForUpdate()
                    ->first();

                if ($lockedExisting) {
                    return [
                        'request' => $lockedExisting,
                        'is_new' => false,
                    ];
                }

                $record = PolicyRequest::create([
                    'agent_id' => $agentId,
                    'client_request_id' => $clientRequestId,
                    'customer_name' => trim((string) $data['customer_name']),
                    'date_of_birth' => $data['date_of_birth'],
                    'destination' => strtoupper(trim((string) $data['destination'])),
                    'trip_start' => $data['trip_start'],
                    'trip_end' => $data['trip_end'],
                    'travel_days' => $pricing->travelDays,
                    'plan_code' => strtoupper(trim((string) $data['plan_code'])),
                    'sum_insured' => (float) $data['sum_insured'],
                    'age_at_trip_start' => $pricing->ageAtTripStart,
                    'base_daily_rate' => $pricing->baseDailyRate,
                    'age_multiplier' => $pricing->ageMultiplier,
                    'destination_multiplier' => $pricing->destinationMultiplier,
                    'coverage_multiplier' => $pricing->coverageMultiplier,
                    'premium_amount' => $pricing->premiumAmount,
                    'pricing_breakdown' => $pricing->breakdown,
                    'status' => PolicyRequest::STATUS_RECEIVED,
                ]);

                // Transition through state machine to record audit trail
                $this->stateMachine->transition(
                    $record,
                    PolicyRequest::STATUS_VALIDATED,
                    'Policy intake validated and premium snapshot calculated'
                );

                return [
                    'request' => $record->fresh(),
                    'is_new' => true,
                ];
            });
        } catch (UniqueConstraintViolationException | QueryException $e) {
            // Concurrent race condition handling: return existing record safely
            $winner = PolicyRequest::query()
                ->where('agent_id', $agentId)
                ->where('client_request_id', $clientRequestId)
                ->first();

            if ($winner) {
                return [
                    'request' => $winner,
                    'is_new' => false,
                ];
            }

            throw $e;
        }
    }
}
