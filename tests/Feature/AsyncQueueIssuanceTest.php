<?php

namespace Tests\Feature;

use App\Jobs\ProcessPolicyIssuanceJob;
use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AsyncQueueIssuanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_with_async_flag_dispatches_job_to_queue(): void
    {
        Queue::fake();

        $policy = PolicyRequest::create([
            'agent_id' => 'AGENT-QUEUE',
            'client_request_id' => 'REQ-QUEUE-01',
            'customer_name' => 'Queue Customer',
            'date_of_birth' => '1995-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-05',
            'travel_days' => 5,
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'age_at_trip_start' => 31,
            'base_daily_rate' => 12.00,
            'age_multiplier' => 1.00,
            'destination_multiplier' => 1.00,
            'coverage_multiplier' => 1.00,
            'premium_amount' => 60.00,
            'status' => PolicyRequest::STATUS_VALIDATED,
        ]);

        $response = $this->postJson("/api/policy-requests/{$policy->id}/submit?async=true", [
            'scenario' => 'SUCCESS',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('queued', true);

        Queue::assertPushed(ProcessPolicyIssuanceJob::class, function ($job) use ($policy) {
            return $job->policyRequestId === $policy->id && $job->scenario === 'SUCCESS';
        });
    }

    public function test_queue_job_executes_issuance_successfully(): void
    {
        $policy = PolicyRequest::create([
            'agent_id' => 'AGENT-QUEUE-EXEC',
            'client_request_id' => 'REQ-QUEUE-EXEC-01',
            'customer_name' => 'Worker Customer',
            'date_of_birth' => '1995-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-05',
            'travel_days' => 5,
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'age_at_trip_start' => 31,
            'base_daily_rate' => 12.00,
            'age_multiplier' => 1.00,
            'destination_multiplier' => 1.00,
            'coverage_multiplier' => 1.00,
            'premium_amount' => 60.00,
            'status' => PolicyRequest::STATUS_VALIDATED,
        ]);

        $job = new ProcessPolicyIssuanceJob($policy->id, 'SUCCESS');
        app()->call([$job, 'handle']);

        $this->assertEquals(PolicyRequest::STATUS_ISSUED, $policy->fresh()->status);
        $this->assertNotNull($policy->fresh()->policy_number);
    }
}
