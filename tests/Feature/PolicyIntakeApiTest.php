<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyIntakeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_request_produces_expected_premium_and_status_201(): void
    {
        // Example from assignment:
        $payload = [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.client_request_id', 'AGT-2026-0001');
        $response->assertJsonPath('data.agent_id', 'AGENT-17');
        $response->assertJsonPath('data.travel_days', 5);
        $this->assertEquals(225.00, $response->json('data.pricing.premium_amount'));
        $response->assertJsonPath('data.status', 'VALIDATED');

        $this->assertDatabaseHas('policy_requests', [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'status' => 'VALIDATED',
            'travel_days' => 5,
        ]);
    }

    public function test_invalid_travel_dates_are_rejected_with_422(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0002',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Jane Doe',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-10',
            'trip_end' => '2026-12-05', // End before start
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['trip_end']);
    }

    public function test_unsupported_plan_and_coverage_are_rejected(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0003',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'John Doe',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-05',
            'plan_code' => 'DIAMOND', // Unsupported
            'sum_insured' => 999999, // Unsupported
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['plan_code', 'sum_insured']);
    }

    public function test_repeated_agent_and_client_request_id_returns_200_and_no_duplicate_row(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-DUP',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Original Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        // First intake
        $firstResponse = $this->postJson('/api/policy-requests', $payload);
        $firstResponse->assertStatus(201);
        $firstId = $firstResponse->json('data.id');

        // Repeated intake with same agent_id + client_request_id
        $secondResponse = $this->postJson('/api/policy-requests', $payload);
        $secondResponse->assertStatus(200);
        $this->assertEquals($firstId, $secondResponse->json('data.id'));

        // Verify only 1 logical record in database
        $this->assertEquals(1, PolicyRequest::where('client_request_id', 'AGT-2026-DUP')->count());
    }

    public function test_can_list_and_filter_policy_requests(): void
    {
        PolicyRequest::create([
            'agent_id' => 'AGENT-01',
            'client_request_id' => 'REQ-01',
            'customer_name' => 'User One',
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
            'status' => 'VALIDATED',
        ]);

        PolicyRequest::create([
            'agent_id' => 'AGENT-02',
            'client_request_id' => 'REQ-02',
            'customer_name' => 'User Two',
            'date_of_birth' => '1985-01-01',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-05',
            'travel_days' => 5,
            'plan_code' => 'PREMIUM',
            'sum_insured' => 500000,
            'age_at_trip_start' => 41,
            'base_daily_rate' => 30.00,
            'age_multiplier' => 1.00,
            'destination_multiplier' => 1.80,
            'coverage_multiplier' => 1.50,
            'premium_amount' => 405.00,
            'status' => 'ISSUED',
            'policy_number' => 'POL-TEST-002',
        ]);

        // Filter by status=ISSUED
        $response = $this->getJson('/api/policy-requests?status=ISSUED');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('POL-TEST-002', $response->json('data.0.policy_number'));

        // Filter by agent_id=AGENT-01
        $agentResponse = $this->getJson('/api/policy-requests?agent_id=AGENT-01');
        $agentResponse->assertStatus(200);
        $this->assertCount(1, $agentResponse->json('data'));
        $this->assertEquals('AGENT-01', $agentResponse->json('data.0.agent_id'));
    }

    public function test_can_retrieve_single_policy_request_with_audit_trail(): void
    {
        $payload = [
            'client_request_id' => 'AGT-SHOW-01',
            'agent_id' => 'AGENT-99',
            'customer_name' => 'Audited Customer',
            'date_of_birth' => '1995-02-15',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-03',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        $res = $this->postJson('/api/policy-requests', $payload);
        $id = $res->json('data.id');

        $showRes = $this->getJson("/api/policy-requests/{$id}");
        $showRes->assertStatus(200);
        $showRes->assertJsonPath('data.id', $id);
        $this->assertNotEmpty($showRes->json('data.audit_trail'));
    }
}
