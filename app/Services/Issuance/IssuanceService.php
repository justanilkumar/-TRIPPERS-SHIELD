<?php

namespace App\Services\Issuance;

use App\Exceptions\MockProviderTemporaryException;
use App\Exceptions\MockProviderTimeoutException;
use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use App\Services\MockInsurer\MockInsurerService;
use App\Services\StateMachine\PolicyStateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IssuanceService
{
    public function __construct(
        protected MockInsurerService $mockInsurer,
        protected PolicyStateMachine $stateMachine
    ) {}

    /**
     * Start or safely resume issuance of a policy request.
     */
    public function submit(PolicyRequest $policyRequest, ?string $scenario = null): PolicyRequest
    {
        // 1. Concurrency lock on this policy request
        return DB::transaction(function () use ($policyRequest, $scenario) {
            $locked = PolicyRequest::query()->lockForUpdate()->findOrFail($policyRequest->id);

            // Avoid making repeated provider calls if already issued
            if ($locked->isIssued()) {
                Log::info("Issuance skipped: PolicyRequest #{$locked->id} is already in ISSUED state.");
                return $locked;
            }

            // Ensure transition to SUBMITTED is allowed
            $this->stateMachine->transition(
                $locked,
                PolicyRequest::STATUS_SUBMITTED,
                'Initiating policy issuance submission',
                ['scenario' => $scenario]
            );

            $attemptNumber = $locked->attempt_count + 1;
            $locked->attempt_count = $attemptNumber;
            $locked->save();

            $attemptRecord = IssuanceAttempt::create([
                'policy_request_id' => $locked->id,
                'attempt_number' => $attemptNumber,
                'scenario' => $scenario,
                'request_payload' => [
                    'agent_id' => $locked->agent_id,
                    'client_request_id' => $locked->client_request_id,
                    'customer_name' => $locked->customer_name,
                    'plan_code' => $locked->plan_code,
                    'sum_insured' => (float) $locked->sum_insured,
                    'premium_amount' => (float) $locked->premium_amount,
                ],
                'status' => IssuanceAttempt::STATUS_PENDING,
            ]);

            try {
                $response = $this->mockInsurer->submitPolicy($locked, $scenario);

                if (($response['status'] ?? '') === 'SUCCESS') {
                    $attemptRecord->update([
                        'response_payload' => $response,
                        'http_status' => 200,
                        'status' => IssuanceAttempt::STATUS_SUCCESS,
                    ]);

                    $locked->policy_number = $response['policy_number'];
                    $locked->provider_reference = $response['provider_reference'];
                    $locked->issued_at = now();
                    $locked->last_error = null;
                    $locked->save();

                    $this->stateMachine->transition(
                        $locked,
                        PolicyRequest::STATUS_ISSUED,
                        'Policy issued successfully by insurer',
                        $response
                    );
                } elseif (($response['status'] ?? '') === 'PENDING') {
                    $attemptRecord->update([
                        'response_payload' => $response,
                        'http_status' => 202,
                        'status' => IssuanceAttempt::STATUS_PENDING,
                    ]);

                    $locked->provider_reference = $response['provider_reference'];
                    $locked->save();

                    $this->stateMachine->transition(
                        $locked,
                        PolicyRequest::STATUS_PENDING_CONFIRMATION,
                        'Awaiting delayed callback confirmation',
                        $response
                    );
                }

                return $locked->fresh(['attempts', 'stateHistory']);

            } catch (MockProviderTimeoutException $e) {
                $attemptRecord->update([
                    'http_status' => 504,
                    'status' => IssuanceAttempt::STATUS_TIMED_OUT,
                    'error_message' => $e->getMessage(),
                ]);

                if ($e->providerReference) {
                    $locked->provider_reference = $e->providerReference;
                }
                $locked->last_error = $e->getMessage();
                $locked->save();

                $this->stateMachine->transition(
                    $locked,
                    PolicyRequest::STATUS_NEEDS_RECONCILIATION,
                    'Timeout during issuance: requires provider reconciliation before retry',
                    ['provider_reference' => $e->providerReference, 'error' => $e->getMessage()]
                );

                return $locked->fresh(['attempts', 'stateHistory']);

            } catch (MockProviderTemporaryException $e) {
                $attemptRecord->update([
                    'http_status' => 503,
                    'status' => IssuanceAttempt::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);

                $locked->last_error = $e->getMessage();
                $locked->save();

                $this->stateMachine->transition(
                    $locked,
                    PolicyRequest::STATUS_FAILED,
                    'Temporary provider failure: safe to retry',
                    ['retriable' => true, 'error' => $e->getMessage()]
                );

                return $locked->fresh(['attempts', 'stateHistory']);
            }
        });
    }

    /**
     * Reconcile a request against the mock insurer.
     */
    public function reconcile(PolicyRequest $policyRequest): PolicyRequest
    {
        return DB::transaction(function () use ($policyRequest) {
            $locked = PolicyRequest::query()->lockForUpdate()->findOrFail($policyRequest->id);

            if ($locked->isIssued()) {
                return $locked;
            }

            // Lookup provider status
            $lookupKey = $locked->provider_reference ?: $locked->client_request_id;
            $providerPolicy = $this->mockInsurer->checkStatus($lookupKey);

            if ($providerPolicy && $providerPolicy->status === 'ISSUED') {
                $locked->policy_number = $providerPolicy->policy_number;
                $locked->provider_reference = $providerPolicy->provider_reference;
                $locked->issued_at = now();
                $locked->last_error = null;
                $locked->save();

                $this->stateMachine->transition(
                    $locked,
                    PolicyRequest::STATUS_ISSUED,
                    'Reconciled successfully: provider confirmed issuance',
                    ['provider_policy_id' => $providerPolicy->id]
                );
            } elseif ($providerPolicy && $providerPolicy->status === 'REJECTED') {
                $this->stateMachine->transition(
                    $locked,
                    PolicyRequest::STATUS_FAILED,
                    'Reconciliation determined provider rejected issuance'
                );
            }

            return $locked->fresh(['attempts', 'stateHistory']);
        });
    }

    /**
     * Process an incoming provider callback event idempotently.
     */
    public function processCallback(array $payload): array
    {
        $eventId = $payload['event_id'] ?? null;
        if (!$eventId) {
            return [
                'success' => false,
                'message' => 'Missing event_id in callback payload',
            ];
        }

        // Idempotency check 1: Event already logged
        $existingEvent = ProviderEvent::where('event_id', $eventId)->first();
        if ($existingEvent) {
            return [
                'success' => true,
                'idempotent' => true,
                'message' => 'Duplicate callback ignored: event_id already processed.',
                'event_id' => $eventId,
            ];
        }

        $providerRef = $payload['provider_reference'] ?? null;
        $clientRequestId = $payload['client_request_id'] ?? null;

        // Locate policy request
        $policyRequest = PolicyRequest::query()
            ->when($providerRef, fn($q) => $q->where('provider_reference', $providerRef))
            ->when(!$providerRef && $clientRequestId, fn($q) => $q->where('client_request_id', $clientRequestId))
            ->first();

        return DB::transaction(function () use ($payload, $eventId, $providerRef, $policyRequest) {
            $event = ProviderEvent::create([
                'event_id' => $eventId,
                'provider_reference' => $providerRef ?: 'UNKNOWN',
                'policy_request_id' => $policyRequest?->id,
                'event_type' => $payload['event_type'] ?? 'UNKNOWN',
                'policy_number' => $payload['policy_number'] ?? null,
                'payload' => $payload,
                'processed' => false,
            ]);

            if (!$policyRequest) {
                $event->update([
                    'processed' => false,
                    'processing_notes' => 'No matching policy request found for provider reference',
                ]);

                return [
                    'success' => false,
                    'message' => 'No matching policy request found',
                    'event_id' => $eventId,
                ];
            }

            $locked = PolicyRequest::query()->lockForUpdate()->findOrFail($policyRequest->id);

            // Idempotency check 2: Request is already ISSUED
            if ($locked->isIssued()) {
                $event->update([
                    'processed' => true,
                    'processing_notes' => 'Policy already confirmed ISSUED. State preserved without regression.',
                ]);

                return [
                    'success' => true,
                    'idempotent' => true,
                    'message' => 'Policy is already in confirmed ISSUED state. Duplicate callback ignored.',
                    'event_id' => $eventId,
                    'policy_number' => $locked->policy_number,
                ];
            }

            $status = strtoupper($payload['status'] ?? 'ISSUED');

            if ($status === 'ISSUED') {
                $locked->policy_number = $payload['policy_number'] ?? $locked->policy_number;
                $locked->issued_at = now();
                $locked->last_error = null;
                $locked->save();

                $this->stateMachine->transition(
                    $locked,
                    PolicyRequest::STATUS_ISSUED,
                    "Issued via provider callback [Event: {$eventId}]",
                    $payload
                );

                $event->update([
                    'processed' => true,
                    'processing_notes' => 'Successfully transitioned to ISSUED.',
                ]);

                return [
                    'success' => true,
                    'idempotent' => false,
                    'message' => 'Policy successfully marked ISSUED from callback.',
                    'event_id' => $eventId,
                    'policy_number' => $locked->policy_number,
                ];
            } elseif ($status === 'FAILED') {
                $this->stateMachine->transition(
                    $locked,
                    PolicyRequest::STATUS_FAILED,
                    "Provider callback indicated failure [Event: {$eventId}]",
                    $payload
                );

                $event->update([
                    'processed' => true,
                    'processing_notes' => 'Transitioned to FAILED from callback.',
                ]);

                return [
                    'success' => true,
                    'idempotent' => false,
                    'message' => 'Policy marked FAILED from callback.',
                    'event_id' => $eventId,
                ];
            }

            return [
                'success' => true,
                'idempotent' => false,
                'message' => 'Callback recorded with pending status.',
                'event_id' => $eventId,
            ];
        });
    }
}
