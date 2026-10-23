<?php

namespace App\Console\Commands;

use App\Services\Intake\BulkCsvIntakeService;
use Illuminate\Console\Command;

class BulkIntakePoliciesCommand extends Command
{
    protected $signature = 'policyflow:bulk-intake {file : Path to CSV file}';
    protected $description = 'Import and process travel policy requests from a CSV file with row-level error reporting and duplicate protection';

    public function handle(BulkCsvIntakeService $bulkService): int
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $content = file_get_contents($filePath);
        $this->info("Processing bulk CSV: {$filePath}...");

        $report = $bulkService->process($content);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Rows Processed', $report['total_rows']],
                ['Created (Validated)', $report['created']],
                ['Duplicates (Safely Ignored)', $report['duplicates']],
                ['Failed / Errors', $report['failed']],
            ]
        );

        if (!empty($report['row_results'])) {
            $tableRows = [];
            foreach (array_slice($report['row_results'], 0, 25) as $res) {
                $tableRows[] = [
                    'Row' => $res['row'],
                    'Client Request ID' => $res['client_request_id'] ?? 'N/A',
                    'Status' => $res['status'],
                    'Details' => !empty($res['errors']) ? implode('; ', $res['errors']) : ($res['message'] ?? 'OK'),
                ];
            }
            $this->info('Sample Row Breakdown (First 25):');
            $this->table(['Row', 'Client Request ID', 'Status', 'Details'], $tableRows);
        }

        return self::SUCCESS;
    }
}
