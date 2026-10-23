<?php

namespace App\Console\Commands;

use App\Models\PolicyRequest;
use App\Services\Issuance\IssuanceService;
use Illuminate\Console\Command;

class ReconcilePoliciesCommand extends Command
{
    protected $signature = 'policyflow:reconcile {--id= : Reconcile a specific policy request ID}';
    protected $description = 'Detect stale PENDING_CONFIRMATION and NEEDS_RECONCILIATION requests and reconcile with external mock provider';

    public function handle(IssuanceService $issuanceService): int
    {
        $specificId = $this->option('id');

        $query = PolicyRequest::query()->whereIn('status', [
            PolicyRequest::STATUS_PENDING_CONFIRMATION,
            PolicyRequest::STATUS_NEEDS_RECONCILIATION,
        ]);

        if ($specificId) {
            $query->where('id', $specificId);
        }

        $policies = $query->get();

        if ($policies->isEmpty()) {
            $this->info('No policies currently require reconciliation.');
            return self::SUCCESS;
        }

        $this->info("Found {$policies->count()} policy request(s) requiring reconciliation.");

        $rows = [];
        $reconciledCount = 0;

        foreach ($policies as $policy) {
            $prevStatus = $policy->status;
            $updated = $issuanceService->reconcile($policy);

            $newStatus = $updated->status;
            if ($newStatus === PolicyRequest::STATUS_ISSUED) {
                $reconciledCount++;
            }

            $rows[] = [
                'ID' => $updated->id,
                'Client Request ID' => $updated->client_request_id,
                'Previous Status' => $prevStatus,
                'New Status' => $newStatus,
                'Policy Number' => $updated->policy_number ?? 'N/A',
                'Provider Ref' => $updated->provider_reference ?? 'N/A',
            ];
        }

        $this->table(['ID', 'Client Request ID', 'Previous Status', 'New Status', 'Policy Number', 'Provider Ref'], $rows);
        $this->info("Reconciliation complete. {$reconciledCount} policy(ies) transitioned to ISSUED.");

        return self::SUCCESS;
    }
}
