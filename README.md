# PolicyFlow: Fault-Tolerant Insurance Issuance & Reconciliation Engine

**Reviewer:** [@MasterAdmin2](https://github.com/MasterAdmin2)

> **TrippersShield Campus Engineering Practical Assessment**  
> Built with **PHP 8.2+**, **Laravel 11**, **SQLite / MySQL 8+**, **REST APIs**, and **Deterministic FSM**.

---

## 📌 Executive Summary
**PolicyFlow** is a fault-tolerant insurance issuance and reconciliation engine engineered to safely process policy purchase requests from travel agents. The system guarantees:
- **Zero Duplicate Issuances**: High-concurrency intake deduplication using composite database constraints (`agent_id` + `client_request_id`) and transactional row locking.
- **Deterministic Pricing**: Actuarial calculations executed with decimal-safe arithmetic (`BCMath`) and immutable snapshot persistence.
- **Resilient Workflow State Machine**: Explicit transitions (`RECEIVED` → `VALIDATED` → `SUBMITTED` → `PENDING_CONFIRMATION` / `NEEDS_RECONCILIATION` → `ISSUED` / `FAILED`) preventing state corruption and illegal regressions.
- **End-to-End Reliability**: Comprehensive automated solutions for **all 4 assessment reliability failure scenarios** (duplicate intake, timeout after issuance, duplicate callbacks, and retries after temporary failures).

---

## 🏗️ Architecture & State Machine

```
                      +---------------------------------------+
                      |               INTAKE                  |
                      |  POST /api/policy-requests            |
                      |  Idempotent (agent_id + req_id)       |
                      +-------------------+-------------------+
                                          |
                                          v
                              +-----------------------+
                              |       RECEIVED        |
                              +-----------+-----------+
                                          | (Validation & Pricing)
                                          v
                              +-----------------------+
                              |       VALIDATED       |
                              +-----------+-----------+
                                          |
                   +----------------------+----------------------+
                   | (POST /api/policy-requests/{id}/submit)      |
                   v                                             v
       +-----------------------+                    +-----------------------+
       |       SUBMITTED       |                    |        FAILED         |
       +-----------+-----------+                    | (Retriable 503)       |
                   |                                +-----------+-----------+
    +--------------+---------------+                            |
    | (Success)    | (Timeout)     | (Async review)             | (Safe Retry)
    v              v               v                            +--------> [SUBMITTED]
+--------+ +--------------------+ +----------------------+
| ISSUED | |NEEDS_RECONCILIATION| | PENDING_CONFIRMATION |
+--------+ +---------+----------+ +-----------+----------+
 (Final)             | (Reconcile)            | (Idempotent Callback)
                     +------------------------+
                                  |
                                  v
                               [ISSUED]
```

### Key Architectural Principles
1. **Pessimistic Locking & Database Concurrency**:
   - `PolicyRequest::query()->lockForUpdate()` is utilized across state transitions and submissions to ensure concurrent attempts do not race.
   - Composite unique key `uq_agent_client_request (agent_id, client_request_id)` ensures duplicate intake requests return the existing record (`HTTP 200`) without creating redundant database records.
2. **Immutable Snapshots**:
   - Pricing calculation inputs, multipliers, and final amounts are locked upon validation in `policy_requests.pricing_breakdown`. Retries never recalculate or alter the approved premium.
3. **Audit Trail**:
   - Every status change logs an audit record in `policy_state_history` with `from_status`, `to_status`, `reason`, and contextual metadata.
4. **Mock Insurer Service**:
   - Simulates a real-world external insurer with deterministic scenarios: `SUCCESS`, `TIMEOUT_AFTER_ISSUE`, `TEMPORARY_FAILURE`, `DELAYED_CALLBACK`, `DUPLICATE_CALLBACK`. Includes a provider status lookup mechanism for reconciliation.

---

## ⚡ Quick Setup & Running Locally

### 1. Requirements
- PHP 8.2 or newer with extensions: `pdo_sqlite` (or `pdo_mysql`), `bcmath`, `mbstring`, `curl`
- Composer 2.x

### 2. Installation
```bash
# Clone the repository and navigate into it
git clone <repo-url> policyflow
cd policyflow

# Install PHP dependencies
composer install

# Environment setup
cp .env.example .env
php artisan key:generate

# Run database migrations
touch database/database.sqlite
php artisan migrate
```

### 3. Running Automated Tests
```bash
# Run all unit and feature tests
php artisan test
```

### 4. Running the Local Server
```bash
# Start the local development server
php artisan serve
```
Open your browser at **`http://127.0.0.1:8000`** to view the interactive PolicyFlow Dashboard and API Playground.

---

## 📡 API Contract

| Method | Endpoint | Description | Status Codes |
|---|---|---|---|
| `POST` | `/api/policy-requests` | Intake policy request (idempotent) | `201 Created` (new), `200 OK` (duplicate), `422 Unprocessable` |
| `GET` | `/api/policy-requests` | List policies with pagination & filters | `200 OK` |
| `GET` | `/api/policy-requests/{id}` | Get policy status, snapshot & audit trail | `200 OK`, `404 Not Found` |
| `POST` | `/api/policy-requests/{id}/submit` | Start or resume issuance | `200 OK`, `422 Unprocessable` |
| `POST` | `/api/policy-requests/{id}/reconcile` | Reconcile pending/stuck policy with provider | `200 OK` |
| `POST` | `/api/mock-insurer/callback` | Webhook receiver for provider events | `200 OK` (idempotent), `422 Unprocessable` |
| `GET` | `/api/mock-insurer/status/{ref}` | External provider status lookup | `200 OK`, `404 Not Found` |
| `POST` | `/api/policy-requests/bulk-csv` | Bulk intake policies via CSV | `200 OK` |

### Sample Intake Request (`POST /api/policy-requests`)
```json
{
  "client_request_id": "AGT-2026-0001",
  "agent_id": "AGENT-17",
  "customer_name": "Sample Customer",
  "date_of_birth": "1998-04-10",
  "destination": "INTERNATIONAL",
  "trip_start": "2026-11-10",
  "trip_end": "2026-11-14",
  "plan_code": "PLUS",
  "sum_insured": 300000
}
```

### Response (`HTTP 201 Created`)
```json
{
  "data": {
    "id": 1,
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "travel_days": 5,
    "plan_code": "PLUS",
    "sum_insured": 300000,
    "pricing": {
      "base_daily_rate": 20,
      "age_at_trip_start": 28,
      "age_multiplier": 1,
      "destination_multiplier": 1.8,
      "coverage_multiplier": 1.25,
      "premium_amount": 225,
      "breakdown": {
        "formula": "base_daily_rate * travel_days * age_multiplier * destination_multiplier * coverage_multiplier"
      }
    },
    "status": "VALIDATED",
    "policy_number": null,
    "attempt_count": 0
  }
}
```

---

## 🛡️ Reliability Scenarios (Implemented & Tested)

All 4 reliability failure scenarios from Section 3.4 have been implemented and tested:

### 1. Duplicate Intake
- **Problem**: Agents retry or trigger near-simultaneous HTTP POST requests with the same `agent_id` + `client_request_id`.
- **Solution**: Composite database unique constraint `['agent_id', 'client_request_id']` combined with transactional deduplication. First request returns `HTTP 201 Created`; subsequent duplicate requests return `HTTP 200 OK` with the existing record. Zero duplicate database rows are created.
- **Test**: `Tests\Feature\ReliabilityScenariosTest::test_case_1_duplicate_intake_returns_existing_record_and_prevents_duplicate_rows`

### 2. Timeout After Success
- **Problem**: Insurer successfully issues a policy internally, but the HTTP connection drops or times out before the client receives the response. Re-submitting blindly would create a duplicate policy and double-charge the customer.
- **Solution**: The engine catches the timeout, sets the status to `NEEDS_RECONCILIATION`, and records the attempt. When reconciliation runs (via API or CLI), it queries the provider status lookup, verifies the policy was issued, retrieves the existing `policy_number`, and transitions the policy to `ISSUED`.
- **Test**: `Tests\Feature\ReliabilityScenariosTest::test_case_2_timeout_after_success_moves_to_reconciliation_and_reconciles_without_duplicate`

### 3. Duplicate Webhook Callback
- **Problem**: External webhook providers retry delivery 2–3 times for network resilience. Repeated events must not create duplicate state records or regress confirmed states.
- **Solution**: `provider_events` table enforces unique constraint on `event_id`. If an event is re-delivered, the system returns `HTTP 200` with `idempotent: true`. Furthermore, policies in `ISSUED` state are immutable and reject regression transitions.
- **Test**: `Tests\Feature\ReliabilityScenariosTest::test_case_3_duplicate_callback_is_idempotent_and_does_not_corrupt_state`

### 4. Retry After Temporary Failure
- **Problem**: External provider returns a temporary 503 Service Unavailable / rate limit error. Retries must not produce duplicate policies or recalculate pricing.
- **Solution**: Failure is logged in `issuance_attempts` with `http_status: 503`, and policy status transitions to `FAILED`. A subsequent submission safely transitions `FAILED → SUBMITTED → ISSUED`, incrementing the attempt count while preserving the original accepted premium snapshot.
- **Test**: `Tests\Feature\ReliabilityScenariosTest::test_case_4_retry_after_temporary_failure_preserves_premium_and_issues_safely`

---

## 🛠️ CLI Commands & Stretch Features

### 1. Automated Stale Policy Reconciliation
Detects all policies stuck in `PENDING_CONFIRMATION` or `NEEDS_RECONCILIATION` and syncs with provider:
```bash
php artisan policyflow:reconcile
```

### 2. Bulk CSV Policy Intake
Ingest multiple requests from a CSV file with row-level validation and duplicate handling:
```bash
php artisan policyflow:bulk-intake examples/sample-intake.csv
```

### 3. Interactive Web Dashboard
A modern UI is available at `/` featuring live actuarial calculation preview, one-click failure simulation, real-time status badges, and complete audit trail drawers.

---

## ⚠️ Known Limitations & Production Enhancements
1. **Mock Insurer Service**: The mock external insurer simulates HTTP latencies and network failures in-process/in-memory via database state. In production, this would communicate over HTTPS with mTLS / HMAC signed webhooks.
2. **Distributed Queue Driver**: Configured with synchronous/database driver for zero-configuration local evaluation. For 10,000+ requests/sec, Redis + Laravel Horizon with exponential backoff and jitter is recommended.
3. **Webhook Security**: In production, callbacks would verify asymmetric RSA / HMAC-SHA256 signatures (`X-Signature-SHA256`) against provider public keys.
