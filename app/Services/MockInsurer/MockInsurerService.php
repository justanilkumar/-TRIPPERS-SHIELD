<?php

namespace App\Services\MockInsurer;

use App\Exceptions\MockProviderException;
use App\Exceptions\MockProviderTemporaryException;
use App\Exceptions\MockProviderTimeoutException;
use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use Illuminate\Support\Str;

class MockInsurerService
{
    public const SCENARIO_SUCCESS = 'SUCCESS';
    public const SCENARIO_TEMPORARY_FAILURE = 'TEMPORARY_FAILURE';
    public const SCENARIO_TIMEOUT_AFTER_ISSUE = 'TIMEOUT_AFTER_ISSUE';
    public const SCENARIO_DELAYED_CALLBACK = 'DELAYED_CALLBACK';
    public const SCENARIO_DUPLICATE_CALLBACK = 'DUPLICATE_CALLBACK';

    public const SUPPORTED_SCENARIOS = [
        self::SCENARIO_SUCCESS,
        self::SCENARIO_TEMPORARY_FAILURE,
        self::SCENARIO_TIMEOUT_AFTER_ISSUE,
        self::SCENARIO_DELAYED_CALLBACK,
        self::SCENARIO_DUPLICATE_CALLBACK,
    ];

    /**
     * Submit policy issuance request to the mock insurer.
     *
     * @throws MockProviderException
     */
    public function submitPolicy(PolicyRequest $policyRequest, ?string $overrideScenario = null): array
    {
        $scenario = strtoupper(trim($overrideScenario ?: self::SCENARIO_SUCCESS));

        // Generate deterministic yet unique references based on agent and client request
        $refSeed = "{$policyRequest->agent_id}:{$policyRequest->client_request_id}";
        $providerRef = 'REF-' . strtoupper(substr(md5($refSeed), 0, 10));
        $policyNumber = 'POL-' . strtoupper(substr(sha1($refSeed), 0, 10));

        switch ($scenario) {
            case self::SCENARIO_SUCCESS:
                $policy = MockInsurerPolicy::updateOrCreate(
                    ['provider_reference' => $providerRef],
                    [
                        'client_request_id' => $policyRequest->client_request_id,
                        'agent_id' => $policyRequest->agent_id,
                        'customer_name' => $policyRequest->customer_name,
                        'policy_number' => $policyNumber,
                        'status' => 'ISSUED',
                        'scenario' => self::SCENARIO_SUCCESS,
                        'premium' => $policyRequest->premium_amount,
                    ]
                );

                return [
                    'status' => 'SUCCESS',
                    'provider_reference' => $policy->provider_reference,
                    'policy_number' => $policy->policy_number,
                    'message' => 'Policy issued successfully by external mock insurer.',
                ];

            case self::SCENARIO_TEMPORARY_FAILURE:
                throw new MockProviderTemporaryException(
                    'External insurer API rate limit reached / temporary 503 Service Unavailable. Safe to retry.',
                    503
                );

            case self::SCENARIO_TIMEOUT_AFTER_ISSUE:
                // Simulates the worst-case distributed systems scenario:
                // The insurer committed the issuance in its internal database,
                // but the HTTP TCP connection was dropped or timed out before the response reached our client.
                MockInsurerPolicy::updateOrCreate(
                    ['provider_reference' => $providerRef],
                    [
                        'client_request_id' => $policyRequest->client_request_id,
                        'agent_id' => $policyRequest->agent_id,
                        'customer_name' => $policyRequest->customer_name,
                        'policy_number' => $policyNumber,
                        'status' => 'ISSUED',
                        'scenario' => self::SCENARIO_TIMEOUT_AFTER_ISSUE,
                        'premium' => $policyRequest->premium_amount,
                    ]
                );

                throw new MockProviderTimeoutException(
                    'HTTP request to external insurer timed out (504 Gateway Timeout). Outcome unknown at client.',
                    $providerRef,
                    504
                );

            case self::SCENARIO_DELAYED_CALLBACK:
            case self::SCENARIO_DUPLICATE_CALLBACK:
                $policy = MockInsurerPolicy::updateOrCreate(
                    ['provider_reference' => $providerRef],
                    [
                        'client_request_id' => $policyRequest->client_request_id,
                        'agent_id' => $policyRequest->agent_id,
                        'customer_name' => $policyRequest->customer_name,
                        'policy_number' => $policyNumber,
                        'status' => 'PENDING',
                        'scenario' => $scenario,
                        'premium' => $policyRequest->premium_amount,
                    ]
                );

                return [
                    'status' => 'PENDING',
                    'provider_reference' => $policy->provider_reference,
                    'policy_number' => null,
                    'message' => 'Policy issuance is pending underwriting verification. A callback will be delivered asynchronously.',
                ];

            default:
                throw new MockProviderException("Unsupported mock scenario: '{$scenario}'.");
        }
    }

    /**
     * Expose a provider status lookup so a timeout can be resolved safely during reconciliation.
     */
    public function checkStatus(string $referenceOrClientRequestId): ?MockInsurerPolicy
    {
        return MockInsurerPolicy::query()
            ->where('provider_reference', $referenceOrClientRequestId)
            ->orWhere('client_request_id', $referenceOrClientRequestId)
            ->first();
    }

    /**
     * Generate simulated callback payload.
     */
    public function generateCallbackPayload(
        string $providerReference,
        ?string $eventId = null,
        string $status = 'ISSUED'
    ): array {
        $policy = $this->checkStatus($providerReference);
        $policyNumber = $policy?->policy_number ?? ('POL-' . strtoupper(Str::random(10)));

        return [
            'event_id' => $eventId ?: ('EVT-' . Str::uuid()->toString()),
            'provider_reference' => $providerReference,
            'event_type' => ($status === 'ISSUED') ? 'POLICY_ISSUED' : 'POLICY_FAILED',
            'status' => $status,
            'policy_number' => ($status === 'ISSUED') ? $policyNumber : null,
            'client_request_id' => $policy?->client_request_id,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
