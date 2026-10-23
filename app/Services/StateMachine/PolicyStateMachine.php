<?php

namespace App\Services\StateMachine;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Support\Facades\DB;

class PolicyStateMachine
{
    /**
     * Allowed state transitions map: [CurrentState => [AllowedNextStates]]
     */
    private const TRANSITION_MAP = [
        PolicyRequest::STATUS_RECEIVED => [
            PolicyRequest::STATUS_VALIDATED,
            PolicyRequest::STATUS_FAILED,
        ],
        PolicyRequest::STATUS_VALIDATED => [
            PolicyRequest::STATUS_SUBMITTED,
            PolicyRequest::STATUS_FAILED,
        ],
        PolicyRequest::STATUS_SUBMITTED => [
            PolicyRequest::STATUS_ISSUED,
            PolicyRequest::STATUS_PENDING_CONFIRMATION,
            PolicyRequest::STATUS_NEEDS_RECONCILIATION,
            PolicyRequest::STATUS_FAILED,
        ],
        PolicyRequest::STATUS_PENDING_CONFIRMATION => [
            PolicyRequest::STATUS_ISSUED,
            PolicyRequest::STATUS_FAILED,
            PolicyRequest::STATUS_NEEDS_RECONCILIATION,
        ],
        PolicyRequest::STATUS_NEEDS_RECONCILIATION => [
            PolicyRequest::STATUS_ISSUED,
            PolicyRequest::STATUS_FAILED,
            PolicyRequest::STATUS_SUBMITTED,
        ],
        PolicyRequest::STATUS_FAILED => [
            PolicyRequest::STATUS_SUBMITTED, // Allow retry after temporary failure
        ],
        PolicyRequest::STATUS_ISSUED => [
            // Terminal state: No transitions allowed.
        ],
    ];

    /**
     * Check if a transition is valid.
     */
    public function canTransition(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return true;
        }

        $allowed = self::TRANSITION_MAP[$fromStatus] ?? [];

        return in_array($toStatus, $allowed, true);
    }

    /**
     * Transition a policy request to a new status with transaction safety and audit logging.
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(
        PolicyRequest $policyRequest,
        string $targetStatus,
        ?string $reason = null,
        ?array $metadata = null
    ): PolicyRequest {
        if (!in_array($targetStatus, PolicyRequest::VALID_STATUSES, true)) {
            throw new InvalidStateTransitionException("Unknown target status: '{$targetStatus}'.");
        }

        return DB::transaction(function () use ($policyRequest, $targetStatus, $reason, $metadata) {
            // Lock record for update to avoid race conditions
            $lockedRequest = PolicyRequest::query()->lockForUpdate()->findOrFail($policyRequest->id);
            $currentStatus = $lockedRequest->status;

            // Safe idempotent check
            if ($currentStatus === $targetStatus) {
                return $lockedRequest;
            }

            // Verify transition rule
            if (!$this->canTransition($currentStatus, $targetStatus)) {
                throw new InvalidStateTransitionException(
                    "Invalid state regression: Cannot transition policy request #{$lockedRequest->id} from {$currentStatus} to {$targetStatus}."
                );
            }

            // Record state transition history audit record
            PolicyStateHistory::create([
                'policy_request_id' => $lockedRequest->id,
                'from_status' => $currentStatus,
                'to_status' => $targetStatus,
                'reason' => $reason,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);

            $lockedRequest->status = $targetStatus;
            $lockedRequest->save();

            return $lockedRequest;
        });
    }
}
