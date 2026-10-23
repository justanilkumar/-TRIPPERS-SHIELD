<?php

namespace App\Http\Controllers;

use App\Services\Issuance\IssuanceService;
use App\Services\MockInsurer\MockInsurerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MockInsurerController extends Controller
{
    public function __construct(
        protected IssuanceService $issuanceService,
        protected MockInsurerService $mockInsurer
    ) {}

    /**
     * Receive simulated provider event callback.
     * Guaranteed idempotent by event_id and policy state.
     */
    public function handleCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'string'],
            'provider_reference' => ['nullable', 'string'],
            'client_request_id' => ['nullable', 'string'],
            'event_type' => ['nullable', 'string'],
            'policy_number' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
        ]);

        $result = $this->issuanceService->processCallback($request->all());

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Expose provider status lookup so timeout can be resolved safely.
     */
    public function checkStatus(string $reference): JsonResponse
    {
        $policy = $this->mockInsurer->checkStatus($reference);

        if (!$policy) {
            return response()->json([
                'exists' => false,
                'message' => "No mock policy found for reference '{$reference}'",
            ], 404);
        }

        return response()->json([
            'exists' => true,
            'provider_reference' => $policy->provider_reference,
            'client_request_id' => $policy->client_request_id,
            'policy_number' => $policy->policy_number,
            'status' => $policy->status,
            'scenario' => $policy->scenario,
            'premium' => (float) $policy->premium,
            'updated_at' => $policy->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Development helper to trigger a simulated asynchronous callback.
     */
    public function triggerSimulatedCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_reference' => ['required', 'string'],
            'event_id' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:ISSUED,FAILED'],
        ]);

        $payload = $this->mockInsurer->generateCallbackPayload(
            providerReference: $validated['provider_reference'],
            eventId: $validated['event_id'] ?? null,
            status: $validated['status'] ?? 'ISSUED'
        );

        $result = $this->issuanceService->processCallback($payload);

        return response()->json([
            'dispatched_payload' => $payload,
            'processing_result' => $result,
        ]);
    }
}
