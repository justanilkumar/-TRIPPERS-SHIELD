<?php

namespace App\Jobs;

use App\Models\PolicyRequest;
use App\Services\Issuance\IssuanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPolicyIssuanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly int $policyRequestId,
        public readonly ?string $scenario = null
    ) {}

    public function handle(IssuanceService $issuanceService): void
    {
        $policy = PolicyRequest::find($this->policyRequestId);
        if (!$policy) {
            Log::warning("ProcessPolicyIssuanceJob: PolicyRequest #{$this->policyRequestId} not found.");
            return;
        }

        if ($policy->isIssued()) {
            Log::info("ProcessPolicyIssuanceJob: PolicyRequest #{$policy->id} is already ISSUED. Skipping queue execution.");
            return;
        }

        Log::info("ProcessPolicyIssuanceJob: Executing issuance for PolicyRequest #{$policy->id} [Attempt {$this->attempts()}]");
        $issuanceService->submit($policy, $this->scenario);
    }

    public function failed(Throwable $exception): void
    {
        Log::error("ProcessPolicyIssuanceJob: Permanent failure on PolicyRequest #{$this->policyRequestId}: " . $exception->getMessage());

        $policy = PolicyRequest::find($this->policyRequestId);
        if ($policy && !$policy->isIssued()) {
            $policy->last_error = 'Dead-letter queue recovery: ' . $exception->getMessage();
            $policy->status = PolicyRequest::STATUS_FAILED;
            $policy->save();
        }
    }
}
