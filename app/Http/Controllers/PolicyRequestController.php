<?php

namespace App\Http\Controllers;

use App\Exceptions\PricingValidationException;
use App\Http\Requests\StorePolicyRequest;
use App\Http\Resources\PolicyRequestResource;
use App\Models\PolicyRequest;
use App\Services\Intake\BulkCsvIntakeService;
use App\Services\Intake\PolicyIntakeService;
use App\Services\Issuance\IssuanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PolicyRequestController extends Controller
{
    /**
     * List policy requests with status filter and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PolicyRequest::query()->with(['stateHistory', 'attempts']);

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper(trim($status)));
        }

        if ($agentId = $request->query('agent_id')) {
            $query->where('agent_id', trim($agentId));
        }

        if ($clientRequestId = $request->query('client_request_id')) {
            $query->where('client_request_id', trim($clientRequestId));
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('policy_number', 'like', "%{$search}%")
                  ->orWhere('client_request_id', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $policies = $query->orderBy('id', 'desc')->paginate($perPage);

        return PolicyRequestResource::collection($policies);
    }

    /**
     * Create or safely return existing policy request.
     * HTTP 201 for new, HTTP 200 for duplicate idempotent intake.
     */
    public function store(StorePolicyRequest $request, PolicyIntakeService $intakeService): JsonResponse
    {
        try {
            $result = $intakeService->intake($request->validated());
            $statusCode = $result['is_new'] ? 201 : 200;

            return (new PolicyRequestResource($result['request']))
                ->response()
                ->setStatusCode($statusCode);
        } catch (PricingValidationException $e) {
            return response()->json([
                'message' => 'Pricing validation error',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Retrieve status, premium, references, and audit trail for a request.
     */
    public function show(int $id): JsonResponse
    {
        $policy = PolicyRequest::with(['stateHistory', 'attempts', 'events'])->findOrFail($id);

        return (new PolicyRequestResource($policy))->response();
    }

    /**
     * Start or safely resume issuance.
     */
    public function submit(Request $request, int $id, IssuanceService $issuanceService): JsonResponse
    {
        $policy = PolicyRequest::findOrFail($id);

        // Scenario can be specified in header or payload for testing / deterministic simulation
        $scenario = $request->input('scenario') ?: $request->header('X-Mock-Scenario');

        $updated = $issuanceService->submit($policy, $scenario);

        return (new PolicyRequestResource($updated->load(['stateHistory', 'attempts'])))->response();
    }

    /**
     * Explicit reconciliation endpoint.
     */
    public function reconcile(int $id, IssuanceService $issuanceService): JsonResponse
    {
        $policy = PolicyRequest::findOrFail($id);
        $reconciled = $issuanceService->reconcile($policy);

        return (new PolicyRequestResource($reconciled->load(['stateHistory', 'attempts'])))->response();
    }

    /**
     * Bulk intake policy requests via CSV file or raw CSV text.
     */
    public function bulkCsv(Request $request, BulkCsvIntakeService $bulkService): JsonResponse
    {
        $request->validate([
            'file' => ['required_without:csv_text', 'file', 'mimes:csv,txt'],
            'csv_text' => ['required_without:file', 'string'],
        ]);

        $content = $request->hasFile('file')
            ? file_get_contents($request->file('file')->getRealPath())
            : $request->input('csv_text');

        $report = $bulkService->process($content);

        return response()->json($report, 200);
    }
}
