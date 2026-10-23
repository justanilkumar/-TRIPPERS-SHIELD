<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkCsvIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_csv_intake_creates_records_handles_duplicates_and_reports_errors(): void
    {
        $csvContent = implode("\n", [
            'client_request_id,agent_id,customer_name,date_of_birth,destination,trip_start,trip_end,plan_code,sum_insured',
            'CSV-REQ-001,AGENT-01,Valid User One,1998-04-10,INTERNATIONAL,2026-11-10,2026-11-14,PLUS,300000',
            'CSV-REQ-002,AGENT-01,Valid User Two,1980-05-15,DOMESTIC,2026-12-01,2026-12-05,BASIC,100000',
            'CSV-REQ-001,AGENT-01,Duplicate User,1998-04-10,INTERNATIONAL,2026-11-10,2026-11-14,PLUS,300000', // duplicate
            'CSV-REQ-003,AGENT-01,Invalid Plan,1990-01-01,DOMESTIC,2026-12-01,2026-12-05,GOLD,100000', // invalid plan
        ]);

        $response = $this->postJson('/api/policy-requests/bulk-csv', [
            'csv_text' => $csvContent,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('total_rows', 4);
        $response->assertJsonPath('created', 2);
        $response->assertJsonPath('duplicates', 1);
        $response->assertJsonPath('failed', 1);

        $this->assertEquals(2, PolicyRequest::count());
    }
}
