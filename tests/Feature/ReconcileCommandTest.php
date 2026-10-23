<?php

namespace Tests\Feature;

use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_command_reconciles_stale_policies_to_issued(): void
    {
        $policy = PolicyRequest::create([
            'agent_id' => 'AGENT-REC',
            'client_request_id' => 'REQ-REC-001',
            'customer_name' => 'Reconciled User',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-05',
            'travel_days' => 5,
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'age_at_trip_start' => 36,
            'base_daily_rate' => 12.00,
            'age_multiplier' => 1.00,
            'destination_multiplier' => 1.00,
            'coverage_multiplier' => 1.00,
            'premium_amount' => 60.00,
            'status' => PolicyRequest::STATUS_NEEDS_RECONCILIATION,
            'provider_reference' => 'REF-REC-001',
        ]);

        // Insurer has it issued
        MockInsurerPolicy::create([
            'provider_reference' => 'REF-REC-001',
            'client_request_id' => 'REQ-REC-001',
            'agent_id' => 'AGENT-REC',
            'customer_name' => 'Reconciled User',
            'policy_number' => 'POL-REC-12345',
            'status' => 'ISSUED',
            'premium' => 60.00,
        ]);

        $this->artisan('policyflow:reconcile')
            ->expectsOutputToContain('Found 1 policy request(s) requiring reconciliation.')
            ->expectsOutputToContain('POL-REC-12345')
            ->assertSuccessful();

        $this->assertEquals(PolicyRequest::STATUS_ISSUED, $policy->fresh()->status);
        $this->assertEquals('POL-REC-12345', $policy->fresh()->policy_number);
    }
}
