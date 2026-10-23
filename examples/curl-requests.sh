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

echo "$INTAKE_RESPONSE" | jq . 2>/dev/null || echo "$INTAKE_RESPONSE"
echo ""

POLICY_ID=$(echo "$INTAKE_RESPONSE" | grep -o '"id":[0-9]*' | sed -n '1p' | cut -d: -f2)

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
  }' | jq . 2>/dev/null
echo ""

echo "=== 3. Retrieve Single Policy Request & Audit Trail (GET) ==="
curl -s "$BASE_URL/policy-requests/$POLICY_ID" -H "Accept: application/json" | jq . 2>/dev/null
echo ""

echo "=== 4. Submit Policy Issuance: Success Scenario (POST /submit) ==="
SUBMIT_RES=$(curl -s -X POST "$BASE_URL/policy-requests/$POLICY_ID/submit" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"scenario": "SUCCESS"}')

echo "$SUBMIT_RES" | jq . 2>/dev/null || echo "$SUBMIT_RES"
PROVIDER_REF=$(echo "$SUBMIT_RES" | grep -o '"provider_reference":"[^"]*' | cut -d'"' -f4)
echo ""

echo "=== 5. List Policy Requests with Filters (Status: ISSUED) ==="
curl -s "$BASE_URL/policy-requests?status=ISSUED" -H "Accept: application/json" | jq . 2>/dev/null
echo ""

echo "=== 6. Simulate Failure Scenario: Timeout After Issue ==="
TIMEOUT_RES=$(curl -s -X POST "$BASE_URL/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
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
  }')

TIMEOUT_ID=$(echo "$TIMEOUT_RES" | grep -o '"id":[0-9]*' | sed -n '1p' | cut -d: -f2)

echo "Submitting policy #$TIMEOUT_ID with TIMEOUT_AFTER_ISSUE..."
curl -s -X POST "$BASE_URL/policy-requests/$TIMEOUT_ID/submit" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"scenario": "TIMEOUT_AFTER_ISSUE"}' | jq . 2>/dev/null
echo ""

echo "=== 7. Reconcile Policy Requiring Reconciliation ==="
curl -s -X POST "$BASE_URL/policy-requests/$TIMEOUT_ID/reconcile" \
  -H "Accept: application/json" | jq . 2>/dev/null
echo ""

echo "=== 8. Test Idempotent Duplicate Webhook Callback ==="
CALLBACK_EVENT='{"event_id":"EVT-CURL-002","provider_reference":"'"$PROVIDER_REF"'","status":"ISSUED"}'

echo "First Callback Delivery:"
curl -s -X POST "$BASE_URL/mock-insurer/callback" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$CALLBACK_EVENT" | jq . 2>/dev/null

echo "Second Duplicate Delivery (Safe & Idempotent):"
curl -s -X POST "$BASE_URL/mock-insurer/callback" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$CALLBACK_EVENT" | jq . 2>/dev/null
echo ""

echo "=== Demonstration Completed Successfully ==="
