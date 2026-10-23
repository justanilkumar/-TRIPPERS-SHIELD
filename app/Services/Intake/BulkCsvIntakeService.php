<?php

namespace App\Services\Intake;

use App\Models\PolicyRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BulkCsvIntakeService
{
    public function __construct(
        protected PolicyIntakeService $intakeService
    ) {}

    /**
     * Process bulk CSV policy requests.
     */
    public function process(string $csvContent): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        if (empty($lines)) {
            return [
                'total_rows' => 0,
                'created' => 0,
                'duplicates' => 0,
                'failed' => 0,
                'row_results' => [],
            ];
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn($col) => strtolower(trim($col)), $header);

        $rowResults = [];
        $createdCount = 0;
        $duplicateCount = 0;
        $failedCount = 0;

        foreach ($lines as $index => $line) {
            if (empty(trim($line))) {
                continue;
            }

            $rowNum = $index + 2; // Accounting for 1-based index and header
            $rawCols = str_getcsv($line);
            if (count($rawCols) !== count($header)) {
                $failedCount++;
                $rowResults[] = [
                    'row' => $rowNum,
                    'status' => 'FAILED',
                    'errors' => ['Column count does not match CSV header.'],
                ];
                continue;
            }

            $row = array_combine($header, array_map('trim', $rawCols));

            // Row-level validation
            $validator = Validator::make($row, [
                'client_request_id' => ['required', 'string'],
                'agent_id' => ['required', 'string'],
                'customer_name' => ['required', 'string'],
                'date_of_birth' => ['required', 'date_format:Y-m-d'],
                'destination' => ['required', Rule::in(PolicyRequest::SUPPORTED_DESTINATIONS)],
                'trip_start' => ['required', 'date_format:Y-m-d'],
                'trip_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:trip_start'],
                'plan_code' => ['required', Rule::in(PolicyRequest::SUPPORTED_PLANS)],
                'sum_insured' => ['required', Rule::in(PolicyRequest::SUPPORTED_SUMS_INSURED)],
            ]);

            if ($validator->fails()) {
                $failedCount++;
                $rowResults[] = [
                    'row' => $rowNum,
                    'client_request_id' => $row['client_request_id'] ?? null,
                    'status' => 'VALIDATION_FAILED',
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            try {
                $result = $this->intakeService->intake($row);
                if ($result['is_new']) {
                    $createdCount++;
                    $rowResults[] = [
                        'row' => $rowNum,
                        'client_request_id' => $row['client_request_id'],
                        'status' => 'CREATED',
                        'policy_request_id' => $result['request']->id,
                        'premium' => (float) $result['request']->premium_amount,
                    ];
                } else {
                    $duplicateCount++;
                    $rowResults[] = [
                        'row' => $rowNum,
                        'client_request_id' => $row['client_request_id'],
                        'status' => 'SKIPPED_DUPLICATE',
                        'policy_request_id' => $result['request']->id,
                        'message' => 'Duplicate agent_id + client_request_id safely ignored.',
                    ];
                }
            } catch (\Throwable $e) {
                $failedCount++;
                $rowResults[] = [
                    'row' => $rowNum,
                    'client_request_id' => $row['client_request_id'] ?? null,
                    'status' => 'ERROR',
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        return [
            'total_rows' => $createdCount + $duplicateCount + $failedCount,
            'created' => $createdCount,
            'duplicates' => $duplicateCount,
            'failed' => $failedCount,
            'row_results' => $rowResults,
        ];
    }
}
