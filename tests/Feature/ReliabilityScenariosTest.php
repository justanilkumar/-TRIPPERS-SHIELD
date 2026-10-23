<?php

namespace Tests\Feature;

use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Models\ProviderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReliabilityScenariosTest extends TestCase
{
    use RefreshDatabase;

    private function createSampleIntakePayload(string $clientRequestId = 'AGT-REL-01'): array
    {
        return [
            'client_request_id' => $clientRequestId,
            'agent_id' => 'AGENT-42',
            'customer_name' => 'John Reliable',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];
    }

    /**
     * Case 1: Duplicate Intake Protection
     * The same client_request_id is submitted repeatedly, including simulated near-simultaneous intake.
     */
    public function test_case_1_duplicate_intake_returns_existing_record_and_prevents_duplicate_rows(): void
    {
        $payload = $this->createSampleIntakePayload('AGT-REL-CASE1');

        // First intake: returns 201 Created
        $response1 = $this->postJson('/api/policy-requests', $payload);
        $response1->assertStatus(201);
        $policyId = $response1->json('data.id');
        $this->assertEquals(225.00, $response1->json('data.pricing.premium_amount'));

        // Second intake with same agent_id + client_request_id: returns 200 OK
        $response2 = $this->postJson('/api/policy-requests', $payload);
        $response2->assertStatus(200);
        $this->assertEquals($policyId, $response2->json('data.id'));

        // Third intake with same agent_id + client_request_id: returns 200 OK
        $response3 = $this->postJson('/api/policy-requests', $payload);
        $response3->assertStatus(200);
        $this->assertEquals($policyId, $response3->json('data.id'));

        // Enforce DB level uniqueness
        $count = PolicyRequest::where('agent_id', 'AGENT-42')
            ->where('client_request_id', 'AGT-REL-CASE1')
            ->count();
        $this->assertEquals(1, $count, 'Only 1 logical record should exist for agent_id + client_request_id.');
    }

    /**
     * Case 2: Timeout After Success
     * The insurer may have issued a policy even when the client receives a timeout;
     * do not blindly issue again. Reconciliation safely verifies provider state.
     */
    public function test_case_2_timeout_after_success_moves_to_reconciliation_and_reconciles_without_duplicate(): void
    {
        $payload = $this->createSampleIntakePayload('AGT-REL-CASE2');
        $intakeRes = $this->postJson('/api/policy-requests', $payload);
        $policyId = $intakeRes->json('data.id');

        // Submit with TIMEOUT_AFTER_ISSUE
        $submitRes = $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => 'TIMEOUT_AFTER_ISSUE',
        ]);

        $submitRes->assertStatus(200);
        $submitRes->assertJsonPath('data.status', 'NEEDS_RECONCILIATION');
        $this->assertNull($submitRes->json('data.policy_number'));

        // Verify the insurer created the policy internally
        $mockPolicy = MockInsurerPolicy::where('client_request_id', 'AGT-REL-CASE2')->first();
        $this->assertNotNull($mockPolicy);
        $this->assertEquals('ISSUED', $mockPolicy->status);
        $expectedPolicyNumber = $mockPolicy->policy_number;

        // Perform reconciliation check
        $reconcileRes = $this->postJson("/api/policy-requests/{$policyId}/reconcile");
        $reconcileRes->assertStatus(200);
        $reconcileRes->assertJsonPath('data.status', 'ISSUED');
        $reconcileRes->assertJsonPath('data.policy_number', $expectedPolicyNumber);

        // Verify exactly one policy number exists and status is ISSUED
        $this->assertEquals(1, MockInsurerPolicy::where('client_request_id', 'AGT-REL-CASE2')->count());
        $this->assertEquals($expectedPolicyNumber, PolicyRequest::find($policyId)->policy_number);
    }

    /**
     * Case 3: Duplicate Callback Handling
     * The same provider event is delivered multiple times; processing must be idempotent.
     */
    public function test_case_3_duplicate_callback_is_idempotent_and_does_not_corrupt_state(): void
    {
        $payload = $this->createSampleIntakePayload('AGT-REL-CASE3');
        $intakeRes = $this->postJson('/api/policy-requests', $payload);
        $policyId = $intakeRes->json('data.id');

        // Submit with DELAYED_CALLBACK to set PENDING_CONFIRMATION
        $submitRes = $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => 'DELAYED_CALLBACK',
        ]);
        $providerRef = $submitRes->json('data.provider_reference');

        $callbackPayload = [
            'event_id' => 'EVT-DUP-CASE-3',
            'provider_reference' => $providerRef,
            'client_request_id' => 'AGT-REL-CASE3',
            'event_type' => 'POLICY_ISSUED',
            'policy_number' => 'POL-CASE3-UNIQUE',
            'status' => 'ISSUED',
        ];

        // First callback delivery
        $cb1 = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $cb1->assertStatus(200);
        $cb1->assertJsonPath('success', true);
        $cb1->assertJsonPath('idempotent', false);

        // Second delivery of identical callback
        $cb2 = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $cb2->assertStatus(200);
        $cb2->assertJsonPath('success', true);
        $cb2->assertJsonPath('idempotent', true);

        // Third delivery of identical callback
        $cb3 = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $cb3->assertStatus(200);
        $cb3->assertJsonPath('success', true);
        $cb3->assertJsonPath('idempotent', true);

        // Verify policy status remains ISSUED
        $policy = PolicyRequest::find($policyId);
        $this->assertEquals('ISSUED', $policy->status);
        $this->assertEquals('POL-CASE3-UNIQUE', $policy->policy_number);

        // Verify only 1 ProviderEvent row for this event_id
        $this->assertEquals(1, ProviderEvent::where('event_id', 'EVT-DUP-CASE-3')->count());
    }

    /**
     * Case 4: Retry After Temporary Failure
     * Retries must not produce duplicate policies or lose the original accepted premium snapshot.
     */
    public function test_case_4_retry_after_temporary_failure_preserves_premium_and_issues_safely(): void
    {
        $payload = $this->createSampleIntakePayload('AGT-REL-CASE4');
        $intakeRes = $this->postJson('/api/policy-requests', $payload);
        $policyId = $intakeRes->json('data.id');
        $initialPremium = (float) $intakeRes->json('data.pricing.premium_amount');
        $this->assertEquals(225.00, $initialPremium);

        // Attempt 1: Encounters TEMPORARY_FAILURE (503 Service Unavailable)
        $attempt1 = $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => 'TEMPORARY_FAILURE',
        ]);
        $attempt1->assertStatus(200);
        $attempt1->assertJsonPath('data.status', 'FAILED');
        $this->assertEquals(1, $attempt1->json('data.attempt_count'));

        // Check that original accepted premium snapshot was preserved
        $policyAfterFail = PolicyRequest::find($policyId);
        $this->assertEquals($initialPremium, (float) $policyAfterFail->premium_amount);
        $this->assertNull($policyAfterFail->policy_number);

        // Attempt 2: Retry after temporary failure succeeds
        $attempt2 = $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => 'SUCCESS',
        ]);
        $attempt2->assertStatus(200);
        $attempt2->assertJsonPath('data.status', 'ISSUED');
        $this->assertEquals(2, $attempt2->json('data.attempt_count'));
        $this->assertNotNull($attempt2->json('data.policy_number'));

        // Verify final state and premium integrity
        $policyFinal = PolicyRequest::find($policyId);
        $this->assertEquals('ISSUED', $policyFinal->status);
        $this->assertEquals($initialPremium, (float) $policyFinal->premium_amount);
        $this->assertEquals(2, $policyFinal->attempts()->count());
    }

    /**
     * Guardrail: Repeated callback or request cannot overwrite a confirmed ISSUED state
     */
    public function test_cannot_overwrite_confirmed_issued_state(): void
    {
        $payload = $this->createSampleIntakePayload('AGT-REL-IMMUTABLE');
        $intakeRes = $this->postJson('/api/policy-requests', $payload);
        $policyId = $intakeRes->json('data.id');

        // Issue policy
        $this->postJson("/api/policy-requests/{$policyId}/submit", ['scenario' => 'SUCCESS'])
            ->assertStatus(200);

        $policy = PolicyRequest::find($policyId);
        $this->assertEquals('ISSUED', $policy->status);
        $confirmedPolicyNumber = $policy->policy_number;

        // An out-of-order or late failure callback arrives
        $lateFailureCallback = [
            'event_id' => 'EVT-LATE-FAIL-01',
            'provider_reference' => $policy->provider_reference,
            'client_request_id' => 'AGT-REL-IMMUTABLE',
            'event_type' => 'POLICY_FAILED',
            'status' => 'FAILED',
        ];

        $res = $this->postJson('/api/mock-insurer/callback', $lateFailureCallback);
        $res->assertStatus(200);
        $res->assertJsonPath('idempotent', true);

        // Ensure status is still ISSUED and policy number is untouched
        $policyRefreshed = PolicyRequest::find($policyId);
        $this->assertEquals('ISSUED', $policyRefreshed->status);
        $this->assertEquals($confirmedPolicyNumber, $policyRefreshed->policy_number);
    }
}
