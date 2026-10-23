#!/usr/bin/env bash

# ==============================================================================
# PolicyFlow API Demonstration & Testing Script
# Make sure the server is running: `php artisan serve` (http://127.0.0.1:8000)
# ==============================================================================

BASE_URL="http://127.0.0.1:8000/api"

echo "=== 1. Policy Request Intake (Creates new policy -> HTTP 201) ==="
INTAKE_RESPONSE=$(curl -s -X POST "$BASE_URL/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }')

echo "$INTAKE_RESPONSE" | jq . || echo "$INTAKE_RESPONSE"
echo ""

POLICY_ID=$(echo "$INTAKE_RESPONSE" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)

if [ -z "$POLICY_ID" ]; then
  POLICY_ID=1
fi

echo "=== 2. Duplicate Intake (Same client_request_id + agent_id -> HTTP 200 Idempotent) ==="
curl -s -X POST "$BASE_URL/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }' | jq .
echo ""

echo "=== 3. Retrieve Single Policy Request & Audit Trail (GET) ==="
curl -s "$BASE_URL/policy-requests/$POLICY_ID" -H "Accept: application/json" | jq .
echo ""

echo "=== 4. Submit Policy Issuance: Success Scenario (POST /submit) ==="
curl -s -X POST "$BASE_URL/policy-requests/$POLICY_ID/submit" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"scenario": "SUCCESS"}' | jq .
echo ""

echo "=== 5. List Policy Requests with Filters (Status: ISSUED) ==="
curl -s "$BASE_URL/policy-requests?status=ISSUED" -H "Accept: application/json" | jq .
echo ""

echo "=== 6. Simulate Failure Scenario: Timeout After Issue ==="
TIMEOUT_ID=$(curl -s -X POST "$BASE_URL/policy-requests" \
  -H "Content-Type: application/json" \
  -d '{
    "client_request_id": "AGT-TIMEOUT-'$RANDOM'",
    "agent_id": "AGENT-42",
    "customer_name": "Timeout Traveler",
    "date_of_birth": "1990-01-01",
    "destination": "DOMESTIC",
    "trip_start": "2026-12-01",
    "trip_end": "2026-12-05",
    "plan_code": "BASIC",
    "sum_insured": 100000
  }' | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)

echo "Submitting policy #$TIMEOUT_ID with TIMEOUT_AFTER_ISSUE..."
curl -s -X POST "$BASE_URL/policy-requests/$TIMEOUT_ID/submit" \
  -H "Content-Type: application/json" \
  -d '{"scenario": "TIMEOUT_AFTER_ISSUE"}' | jq .
echo ""

echo "=== 7. Reconcile Policy Requiring Reconciliation ==="
curl -s -X POST "$BASE_URL/policy-requests/$TIMEOUT_ID/reconcile" \
  -H "Accept: application/json" | jq .
echo ""

echo "=== 8. Test Idempotent Duplicate Webhook Callback ==="
CALLBACK_EVENT='{
  "event_id": "EVT-CURL-001",
  "provider_reference": "REF-TEST-001",
  "client_request_id": "AGT-2026-0001",
  "event_type": "POLICY_ISSUED",
  "policy_number": "POL-CURL-001",
  "status": "ISSUED"
}'

echo "First Callback Delivery:"
curl -s -X POST "$BASE_URL/mock-insurer/callback" \
  -H "Content-Type: application/json" \
  -d "$CALLBACK_EVENT" | jq .

echo "Second Duplicate Delivery (Safe & Idempotent):"
curl -s -X POST "$BASE_URL/mock-insurer/callback" \
  -H "Content-Type: application/json" \
  -d "$CALLBACK_EVENT" | jq .
echo ""

echo "=== Demonstration Completed Successfully ==="
