<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyIssuanceApiTest extends TestCase
{
    use RefreshDatabase;

    private function createValidatedPolicyRequest(): PolicyRequest
    {
        return PolicyRequest::create([
            'agent_id' => 'AGENT-10',
            'client_request_id' => 'ISSUE-REQ-' . uniqid(),
            'customer_name' => 'Charlie Day',
            'date_of_birth' => '1992-03-12',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-04',
            'travel_days' => 4,
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
            'age_at_trip_start' => 34,
            'base_daily_rate' => 20.00,
            'age_multiplier' => 1.00,
            'destination_multiplier' => 1.00,
            'coverage_multiplier' => 1.25,
            'premium_amount' => 100.00,
            'status' => PolicyRequest::STATUS_VALIDATED,
        ]);
    }

    public function test_successful_provider_submission_issues_policy_and_sets_unique_policy_number(): void
    {
        $policy = $this->createValidatedPolicyRequest();

        $response = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'SUCCESS',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'ISSUED');
        $this->assertNotNull($response->json('data.policy_number'));
        $this->assertNotNull($response->json('data.provider_reference'));
        $this->assertEquals(1, $response->json('data.attempt_count'));

        $this->assertDatabaseHas('policy_requests', [
            'id' => $policy->id,
            'status' => 'ISSUED',
        ]);

        $this->assertDatabaseHas('issuance_attempts', [
            'policy_request_id' => $policy->id,
            'status' => 'SUCCESS',
            'http_status' => 200,
        ]);
    }

    public function test_repeated_submit_on_already_issued_policy_is_idempotent_and_does_not_call_provider_again(): void
    {
        $policy = $this->createValidatedPolicyRequest();

        // First submit: Issues policy
        $this->postJson("/api/policy-requests/{$policy->id}/submit", ['scenario' => 'SUCCESS'])
            ->assertStatus(200);

        $policyNumber = $policy->fresh()->policy_number;
        $attemptCount = $policy->fresh()->attempt_count;

        // Second submit: Should be safely ignored
        $secondResponse = $this->postJson("/api/policy-requests/{$policy->id}/submit", ['scenario' => 'SUCCESS']);
        $secondResponse->assertStatus(200);
        $secondResponse->assertJsonPath('data.status', 'ISSUED');
        $this->assertEquals($policyNumber, $secondResponse->json('data.policy_number'));
        $this->assertEquals($attemptCount, $policy->fresh()->attempt_count);
    }

    public function test_delayed_callback_scenario_moves_to_pending_confirmation_and_resolves_via_webhook(): void
    {
        $policy = $this->createValidatedPolicyRequest();

        // Submit with DELAYED_CALLBACK
        $submitRes = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'DELAYED_CALLBACK',
        ]);

        $submitRes->assertStatus(200);
        $submitRes->assertJsonPath('data.status', 'PENDING_CONFIRMATION');
        $providerRef = $submitRes->json('data.provider_reference');
        $this->assertNotNull($providerRef);

        // Later, external provider posts callback event
        $callbackPayload = [
            'event_id' => 'EVT-WEBHOOK-999',
            'provider_reference' => $providerRef,
            'event_type' => 'POLICY_ISSUED',
            'policy_number' => 'POL-EXTERNAL-999',
            'status' => 'ISSUED',
        ];

        $callbackRes = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $callbackRes->assertStatus(200);
        $callbackRes->assertJsonPath('success', true);
        $callbackRes->assertJsonPath('idempotent', false);

        // Verify policy request updated to ISSUED
        $this->assertEquals('ISSUED', $policy->fresh()->status);
        $this->assertEquals('POL-EXTERNAL-999', $policy->fresh()->policy_number);
    }
}
