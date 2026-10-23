<?php

namespace Tests\Unit;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Services\StateMachine\PolicyStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private PolicyStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new PolicyStateMachine();
    }

    private function createSamplePolicyRequest(string $status = PolicyRequest::STATUS_RECEIVED): PolicyRequest
    {
        return PolicyRequest::create([
            'agent_id' => 'AGENT-01',
            'client_request_id' => 'REQ-' . uniqid(),
            'customer_name' => 'Alice Doe',
            'date_of_birth' => '1995-05-10',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-05',
            'travel_days' => 5,
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
            'age_at_trip_start' => 31,
            'base_daily_rate' => 20.00,
            'age_multiplier' => 1.00,
            'destination_multiplier' => 1.00,
            'coverage_multiplier' => 1.25,
            'premium_amount' => 125.00,
            'status' => $status,
        ]);
    }

    public function test_can_transition_through_happy_path(): void
    {
        $request = $this->createSamplePolicyRequest(PolicyRequest::STATUS_RECEIVED);

        $this->stateMachine->transition($request, PolicyRequest::STATUS_VALIDATED, 'Validation passed');
        $this->assertEquals(PolicyRequest::STATUS_VALIDATED, $request->fresh()->status);

        $this->stateMachine->transition($request, PolicyRequest::STATUS_SUBMITTED, 'Submitting to mock insurer');
        $this->assertEquals(PolicyRequest::STATUS_SUBMITTED, $request->fresh()->status);

        $this->stateMachine->transition($request, PolicyRequest::STATUS_ISSUED, 'Policy issued successfully');
        $this->assertEquals(PolicyRequest::STATUS_ISSUED, $request->fresh()->status);

        // Verify audit history created
        $history = PolicyStateHistory::where('policy_request_id', $request->id)->get();
        $this->assertCount(3, $history);
        $this->assertEquals(PolicyRequest::STATUS_RECEIVED, $history[0]->from_status);
        $this->assertEquals(PolicyRequest::STATUS_VALIDATED, $history[0]->to_status);
        $this->assertEquals(PolicyRequest::STATUS_VALIDATED, $history[1]->from_status);
        $this->assertEquals(PolicyRequest::STATUS_SUBMITTED, $history[1]->to_status);
        $this->assertEquals(PolicyRequest::STATUS_SUBMITTED, $history[2]->from_status);
        $this->assertEquals(PolicyRequest::STATUS_ISSUED, $history[2]->to_status);
    }

    public function test_rejects_invalid_regression_from_issued_to_submitted(): void
    {
        $request = $this->createSamplePolicyRequest(PolicyRequest::STATUS_ISSUED);

        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage('Invalid state regression: Cannot transition policy request');

        $this->stateMachine->transition($request, PolicyRequest::STATUS_SUBMITTED, 'Attempting to resubmit issued policy');
    }

    public function test_rejects_invalid_regression_from_issued_to_failed(): void
    {
        $request = $this->createSamplePolicyRequest(PolicyRequest::STATUS_ISSUED);

        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage('Invalid state regression');

        $this->stateMachine->transition($request, PolicyRequest::STATUS_FAILED, 'Late failure received');
    }

    public function test_allows_retry_from_failed_to_submitted(): void
    {
        $request = $this->createSamplePolicyRequest(PolicyRequest::STATUS_FAILED);

        $this->stateMachine->transition($request, PolicyRequest::STATUS_SUBMITTED, 'Retrying issuance after failure');
        $this->assertEquals(PolicyRequest::STATUS_SUBMITTED, $request->fresh()->status);
    }

    public function test_allows_reconciliation_workflow(): void
    {
        $request = $this->createSamplePolicyRequest(PolicyRequest::STATUS_SUBMITTED);

        // Move to NEEDS_RECONCILIATION after timeout
        $this->stateMachine->transition($request, PolicyRequest::STATUS_NEEDS_RECONCILIATION, 'Timeout calling provider');
        $this->assertEquals(PolicyRequest::STATUS_NEEDS_RECONCILIATION, $request->fresh()->status);

        // Reconcile and transition to ISSUED
        $this->stateMachine->transition($request, PolicyRequest::STATUS_ISSUED, 'Reconciled: policy confirmed at provider');
        $this->assertEquals(PolicyRequest::STATUS_ISSUED, $request->fresh()->status);
    }
}
