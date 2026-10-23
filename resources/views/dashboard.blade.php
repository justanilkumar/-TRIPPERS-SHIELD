<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolicyFlow | Fault-Tolerant Insurance Engine</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #0b0f19;
            --bg-card: #111827;
            --bg-card-hover: #17223b;
            --border: #1f293d;
            --border-highlight: #374151;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --primary: #38bdf8;
            --primary-glow: rgba(56, 189, 248, 0.15);
            --success: #10b981;
            --success-bg: rgba(16, 185, 129, 0.15);
            --warning: #f59e0b;
            --warning-bg: rgba(245, 158, 11, 0.15);
            --danger: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.15);
            --purple: #a855f7;
            --purple-bg: rgba(168, 85, 247, 0.15);
            --cyan: #06b6d4;
            --cyan-bg: rgba(6, 182, 212, 0.15);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-base);
            color: var(--text-primary);
            line-height: 1.5;
            min-height: 100vh;
            padding: 24px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 28px;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #0284c7, #38bdf8);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 22px;
            color: #fff;
            box-shadow: 0 0 20px var(--primary-glow);
        }

        .logo-title h1 {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(90deg, #f8fafc, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-title p {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .header-badges {
            display: flex;
            gap: 12px;
        }

        .chip {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid var(--border);
            background: rgba(30, 41, 59, 0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 8px var(--success);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 20px;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            border-color: var(--border-highlight);
            transform: translateY(-2px);
        }

        .stat-title {
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            font-family: 'JetBrains Mono', monospace;
        }

        .stat-issued { color: var(--success); }
        .stat-pending { color: var(--warning); }
        .stat-reconcile { color: var(--purple); }
        .stat-failed { color: var(--danger); }
        .stat-validated { color: var(--primary); }

        /* Playground Grid */
        .workspace-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        @media (max-width: 1024px) {
            .workspace-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 14px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        input, select {
            width: 100%;
            background: #0d131f;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 9px 12px;
            color: var(--text-primary);
            font-size: 13px;
            font-family: inherit;
            transition: border-color 0.15s ease;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-glow);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: #0284c7;
            color: #fff;
            width: 100%;
        }

        .btn-primary:hover {
            background: #0369a1;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-success { background: #059669; color: #fff; }
        .btn-success:hover { background: #047857; }

        .btn-warning { background: #d97706; color: #fff; }
        .btn-warning:hover { background: #b45309; }

        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }

        .btn-purple { background: #7c3aed; color: #fff; }
        .btn-purple:hover { background: #6d28d9; }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-highlight);
            color: var(--text-secondary);
        }

        .btn-outline:hover {
            background: var(--bg-card-hover);
            color: var(--text-primary);
        }

        .calc-preview {
            background: rgba(15, 23, 42, 0.8);
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 14px;
            margin-top: 16px;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .calc-preview .amount {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
            font-family: 'JetBrains Mono', monospace;
        }

        /* Table styles */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        th {
            background: #0d131f;
            padding: 12px 14px;
            font-weight: 700;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
            vertical-align: middle;
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
            color: var(--text-primary);
        }

        .code-tag {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            padding: 3px 6px;
            background: #1e293b;
            border-radius: 4px;
            color: #e2e8f0;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .badge-ISSUED { background: var(--success-bg); color: var(--success); }
        .badge-VALIDATED { background: var(--cyan-bg); color: var(--cyan); }
        .badge-SUBMITTED { background: var(--primary-glow); color: var(--primary); }
        .badge-PENDING_CONFIRMATION { background: var(--warning-bg); color: var(--warning); }
        .badge-NEEDS_RECONCILIATION { background: var(--purple-bg); color: var(--purple); }
        .badge-FAILED { background: var(--danger-bg); color: var(--danger); }
        .badge-RECEIVED { background: #334155; color: #cbd5e1; }

        .actions-cell {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        /* Modal */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            z-index: 100;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            width: 100%;
            max-width: 800px;
            max-height: 85vh;
            overflow-y: auto;
            padding: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }

        .timeline {
            border-left: 2px solid var(--border);
            padding-left: 18px;
            margin: 16px 0;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 16px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 4px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary);
        }

        .alert-box {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
            display: none;
        }
        .alert-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header>
            <div class="logo-area">
                <div class="logo-icon">PF</div>
                <div class="logo-title">
                    <h1>PolicyFlow Engine</h1>
                    <p>Fault-Tolerant Travel Insurance Issuance & Idempotent Reconciliation</p>
                </div>
            </div>
            <div class="header-badges">
                <div class="chip">
                    <span class="dot"></span>
                    <span>Mock Insurer Online</span>
                </div>
                <button class="btn btn-outline btn-sm" onclick="triggerArtisanReconcile()">
                    🔄 Run Reconcile All
                </button>
            </div>
        </header>

        <div id="globalAlert" class="alert-box"></div>

        <!-- Metric Counters -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">Total Requests</div>
                <div class="stat-value" id="stat-total">{{ $stats['total'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Issued (Unique)</div>
                <div class="stat-value stat-issued" id="stat-issued">{{ $stats['issued'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Pending Confirmation</div>
                <div class="stat-value stat-pending" id="stat-pending">{{ $stats['pending'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Needs Reconciliation</div>
                <div class="stat-value stat-reconcile" id="stat-reconcile">{{ $stats['reconcile'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Temporary Failures</div>
                <div class="stat-value stat-failed" id="stat-failed">{{ $stats['failed'] }}</div>
            </div>
        </div>

        <!-- Interactive Workspace -->
        <div class="workspace-grid">
            <!-- Left Column: New Intake & Simulator -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📝 Policy Intake Playground</div>
                    <span class="code-tag">POST /api/policy-requests</span>
                </div>

                <form id="intakeForm" onsubmit="handleIntakeSubmit(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Agent ID</label>
                            <input type="text" id="agent_id" value="AGENT-17" required>
                        </div>
                        <div class="form-group">
                            <label>Client Request ID</label>
                            <input type="text" id="client_request_id" value="AGT-2026-0001" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Customer Name</label>
                        <input type="text" id="customer_name" value="Sample Customer" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" id="date_of_birth" value="1998-04-10" onchange="updateLiveCalculation()" required>
                        </div>
                        <div class="form-group">
                            <label>Destination</label>
                            <select id="destination" onchange="updateLiveCalculation()">
                                <option value="INTERNATIONAL" selected>INTERNATIONAL (1.8x)</option>
                                <option value="DOMESTIC">DOMESTIC (1.0x)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Trip Start</label>
                            <input type="date" id="trip_start" value="2026-11-10" onchange="updateLiveCalculation()" required>
                        </div>
                        <div class="form-group">
                            <label>Trip End</label>
                            <input type="date" id="trip_end" value="2026-11-14" onchange="updateLiveCalculation()" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Plan Code</label>
                            <select id="plan_code" onchange="updateLiveCalculation()">
                                <option value="BASIC">BASIC (₹12/day)</option>
                                <option value="PLUS" selected>PLUS (₹20/day)</option>
                                <option value="PREMIUM">PREMIUM (₹30/day)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sum Insured</label>
                            <select id="sum_insured" onchange="updateLiveCalculation()">
                                <option value="100000">₹100,000 (1.0x)</option>
                                <option value="300000" selected>₹300,000 (1.25x)</option>
                                <option value="500000">₹500,000 (1.50x)</option>
                            </select>
                        </div>
                    </div>

                    <div class="calc-preview">
                        <div>
                            <div>Deterministic Premium</div>
                            <small id="calcBreakdownText" style="color: var(--text-muted);">5 days × ₹20 × 1.00 × 1.80 × 1.25</small>
                        </div>
                        <div class="amount" id="previewAmount">₹225.00</div>
                    </div>

                    <div style="margin-top: 16px;">
                        <button type="submit" class="btn btn-primary" id="btnIntake">
                            ⚡ Submit Policy Intake (201 / 200 Idempotent)
                        </button>
                    </div>
                </form>

                <!-- Reliability Scenario Quick Testing Guide -->
                <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border);">
                    <div style="font-size: 13px; font-weight: 700; margin-bottom: 8px; color: var(--text-secondary);">
                        🧪 Reliability Testing Quick Actions
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
                        Click row action buttons to simulate insurer failures and test reconciliation live.
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button class="btn btn-outline btn-sm" onclick="setPreset('duplicate')">🔄 Test Duplicate Intake</button>
                        <button class="btn btn-outline btn-sm" onclick="setPreset('random')">🎲 Generate Random User</button>
                    </div>
                </div>
            </div>

            <!-- Right Column: Policies Table -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <span>📋 Policy Requests & Audit Trail</span>
                        <span class="chip" style="font-size: 11px;">Live Operational View</span>
                    </div>
                    <button class="btn btn-outline btn-sm" onclick="window.location.reload()">
                        🔄 Refresh
                    </button>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client Req ID</th>
                                <th>Customer</th>
                                <th>Plan</th>
                                <th>Days</th>
                                <th>Premium</th>
                                <th>Status</th>
                                <th>Policy #</th>
                                <th>Attempts</th>
                                <th>Simulate Actions</th>
                            </tr>
                        </thead>
                        <tbody id="policyTableBody">
                            @forelse($policies as $p)
                                <tr id="row-{{ $p->id }}">
                                    <td><span class="code-tag">#{{ $p->id }}</span></td>
                                    <td>
                                        <div style="font-weight: 600; color: #fff;">{{ $p->client_request_id }}</div>
                                        <small style="color: var(--text-muted);">{{ $p->agent_id }}</small>
                                    </td>
                                    <td>{{ $p->customer_name }}</td>
                                    <td><span class="code-tag">{{ $p->plan_code }}</span></td>
                                    <td>{{ $p->travel_days }}d</td>
                                    <td style="font-weight: 700; color: var(--primary);">₹{{ number_format($p->premium_amount, 2) }}</td>
                                    <td>
                                        <span class="status-badge badge-{{ $p->status }}">{{ $p->status }}</span>
                                    </td>
                                    <td>
                                        @if($p->policy_number)
                                            <span class="code-tag" style="color: var(--success);">{{ $p->policy_number }}</span>
                                        @else
                                            <span style="color: var(--text-muted);">—</span>
                                        @endif
                                    </td>
                                    <td><span class="code-tag">{{ $p->attempt_count }}</span></td>
                                    <td>
                                        <div class="actions-cell">
                                            @if($p->status !== 'ISSUED')
                                                <button class="btn btn-success btn-sm" title="Submit with SUCCESS scenario" onclick="submitIssuance({{ $p->id }}, 'SUCCESS')">⚡ Success</button>
                                                <button class="btn btn-purple btn-sm" title="Simulate TIMEOUT_AFTER_ISSUE" onclick="submitIssuance({{ $p->id }}, 'TIMEOUT_AFTER_ISSUE')">⏱️ Timeout</button>
                                                <button class="btn btn-danger btn-sm" title="Simulate 503 TEMPORARY_FAILURE" onclick="submitIssuance({{ $p->id }}, 'TEMPORARY_FAILURE')">⚠️ 503</button>
                                                <button class="btn btn-warning btn-sm" title="Simulate DELAYED_CALLBACK" onclick="submitIssuance({{ $p->id }}, 'DELAYED_CALLBACK')">⏳ Delay</button>
                                            @endif
                                            @if(in_array($p->status, ['NEEDS_RECONCILIATION', 'PENDING_CONFIRMATION']))
                                                <button class="btn btn-outline btn-sm" style="color: var(--purple); border-color: var(--purple);" onclick="reconcilePolicy({{ $p->id }})">🔄 Reconcile</button>
                                            @endif
                                            @if($p->status === 'PENDING_CONFIRMATION')
                                                <button class="btn btn-outline btn-sm" style="color: var(--success);" onclick="dispatchCallback('{{ $p->provider_reference }}')">📨 Callback</button>
                                            @endif
                                            <button class="btn btn-outline btn-sm" onclick="viewAuditTrail({{ $p->id }})">🔍 Audit</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        No policy requests found. Create one using the playground on the left!
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Trail Modal -->
    <div id="auditModal" class="modal-backdrop" onclick="closeModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 id="modalTitle" style="font-size: 18px; font-weight: 700;">Audit Trail & Attempts</h3>
                <button class="btn btn-outline btn-sm" onclick="document.getElementById('auditModal').style.display = 'none'">✕ Close</button>
            </div>
            
            <div id="modalBody">
                <p>Loading details...</p>
            </div>
        </div>
    </div>

    <script>
        function showAlert(msg, isSuccess = true) {
            const box = document.getElementById('globalAlert');
            box.style.display = 'block';
            box.className = 'alert-box ' + (isSuccess ? 'alert-success' : 'alert-error');
            box.innerText = msg;
            setTimeout(() => { box.style.display = 'none'; }, 6000);
        }

        function calculateLivePremium() {
            const plan = document.getElementById('plan_code').value;
            const dest = document.getElementById('destination').value;
            const sum = parseInt(document.getElementById('sum_insured').value);
            const dob = new Date(document.getElementById('date_of_birth').value);
            const start = new Date(document.getElementById('trip_start').value);
            const end = new Date(document.getElementById('trip_end').value);

            if (isNaN(dob) || isNaN(start) || isNaN(end) || end < start) {
                return null;
            }

            const baseRate = plan === 'BASIC' ? 12 : (plan === 'PLUS' ? 20 : 30);
            const destMultiplier = dest === 'INTERNATIONAL' ? 1.80 : 1.00;
            const covMultiplier = sum === 100000 ? 1.00 : (sum === 300000 ? 1.25 : 1.50);

            // Days inclusive
            const diffTime = Math.abs(end - start);
            const travelDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

            // Age at trip start
            let age = start.getFullYear() - dob.getFullYear();
            const m = start.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && start.getDate() < dob.getDate())) {
                age--;
            }

            let ageMultiplier = 1.00;
            if (age <= 17) ageMultiplier = 1.10;
            else if (age <= 60) ageMultiplier = 1.00;
            else if (age <= 70) ageMultiplier = 1.50;
            else ageMultiplier = 2.00;

            const premium = baseRate * travelDays * ageMultiplier * destMultiplier * covMultiplier;
            return {
                travelDays,
                age,
                baseRate,
                ageMultiplier,
                destMultiplier,
                covMultiplier,
                premium: premium.toFixed(2)
            };
        }

        function updateLiveCalculation() {
            const res = calculateLivePremium();
            if (res) {
                document.getElementById('previewAmount').innerText = '₹' + res.premium;
                document.getElementById('calcBreakdownText').innerText = 
                    `${res.travelDays} days × ₹${res.baseRate} × ${res.ageMultiplier.toFixed(2)} × ${res.destMultiplier.toFixed(2)} × ${res.covMultiplier.toFixed(2)}`;
            }
        }

        async function handleIntakeSubmit(e) {
            e.preventDefault();
            const payload = {
                agent_id: document.getElementById('agent_id').value,
                client_request_id: document.getElementById('client_request_id').value,
                customer_name: document.getElementById('customer_name').value,
                date_of_birth: document.getElementById('date_of_birth').value,
                destination: document.getElementById('destination').value,
                trip_start: document.getElementById('trip_start').value,
                trip_end: document.getElementById('trip_end').value,
                plan_code: document.getElementById('plan_code').value,
                sum_insured: parseInt(document.getElementById('sum_insured').value)
            };

            try {
                const res = await fetch('/api/policy-requests', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (res.status === 201) {
                    showAlert(`✅ Newly created Policy Request #${data.data.id} (Status: ${data.data.status}, Premium: ₹${data.data.pricing.premium_amount})`, true);
                    setTimeout(() => window.location.reload(), 1200);
                } else if (res.status === 200) {
                    showAlert(`ℹ️ Idempotent intake: Request #${data.data.id} already exists with status ${data.data.status}. Safely returned existing record without duplicates.`, true);
                } else {
                    showAlert(`❌ Error: ${JSON.stringify(data.errors || data.message)}`, false);
                }
            } catch (err) {
                showAlert('Network request error: ' + err.message, false);
            }
        }

        async function submitIssuance(id, scenario) {
            try {
                const res = await fetch(`/api/policy-requests/${id}/submit`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ scenario: scenario })
                });
                const data = await res.json();
                showAlert(`Issuance submitted under scenario '${scenario}'. Policy is now '${data.data.status}'.`, true);
                setTimeout(() => window.location.reload(), 1000);
            } catch (err) {
                showAlert('Error submitting issuance: ' + err.message, false);
            }
        }

        async function reconcilePolicy(id) {
            try {
                const res = await fetch(`/api/policy-requests/${id}/reconcile`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
                });
                const data = await res.json();
                showAlert(`Reconciliation executed for #${id}. State: ${data.data.status}, Policy #: ${data.data.policy_number || 'N/A'}`, true);
                setTimeout(() => window.location.reload(), 1000);
            } catch (err) {
                showAlert('Reconciliation error: ' + err.message, false);
            }
        }

        async function dispatchCallback(providerRef) {
            try {
                const res = await fetch('/api/mock-insurer/dispatch-callback', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ provider_reference: providerRef, status: 'ISSUED' })
                });
                const data = await res.json();
                showAlert(`Callback dispatched: ${data.processing_result.message}`, true);
                setTimeout(() => window.location.reload(), 1000);
            } catch (err) {
                showAlert('Error dispatching callback: ' + err.message, false);
            }
        }

        async function triggerArtisanReconcile() {
            try {
                // Call reconcile for all pending or needs_reconciliation
                const res = await fetch('/api/policy-requests?per_page=100');
                const json = await res.json();
                const candidates = json.data.filter(p => ['NEEDS_RECONCILIATION', 'PENDING_CONFIRMATION'].includes(p.status));
                if (candidates.length === 0) {
                    showAlert('No policies currently require reconciliation.', true);
                    return;
                }
                for (const c of candidates) {
                    await fetch(`/api/policy-requests/${c.id}/reconcile`, { method: 'POST' });
                }
                showAlert(`Reconciliation triggered for ${candidates.length} policy(ies).`, true);
                setTimeout(() => window.location.reload(), 1000);
            } catch (err) {
                showAlert('Error: ' + err.message, false);
            }
        }

        async function viewAuditTrail(id) {
            document.getElementById('auditModal').style.display = 'flex';
            document.getElementById('modalTitle').innerText = `Policy #${id} Details & Audit Trail`;
            document.getElementById('modalBody').innerHTML = '<p>Loading audit logs...</p>';

            try {
                const res = await fetch(`/api/policy-requests/${id}`);
                const json = await res.json();
                const p = json.data;

                let historyHtml = '<div class="timeline">';
                if (p.audit_trail && p.audit_trail.length > 0) {
                    p.audit_trail.forEach(h => {
                        historyHtml += `
                            <div class="timeline-item">
                                <div style="font-weight: 700; color: #fff;">${h.from_status || 'INITIAL'} → <span class="status-badge badge-${h.to_status}">${h.to_status}</span></div>
                                <div style="font-size: 12px; color: var(--text-muted);">${h.timestamp}</div>
                                <div style="font-size: 13px; margin-top: 4px;">${h.reason || 'N/A'}</div>
                            </div>
                        `;
                    });
                } else {
                    historyHtml += '<p style="color: var(--text-muted);">No state transitions recorded.</p>';
                }
                historyHtml += '</div>';

                let attemptsHtml = '<div style="margin-top: 20px;"><h4 style="margin-bottom: 10px;">Issuance Attempts</h4>';
                if (p.attempts && p.attempts.length > 0) {
                    attemptsHtml += '<table><thead><tr><th>#</th><th>Scenario</th><th>HTTP</th><th>Status</th><th>Time</th></tr></thead><tbody>';
                    p.attempts.forEach(a => {
                        attemptsHtml += `
                            <tr>
                                <td>${a.attempt_number}</td>
                                <td><span class="code-tag">${a.scenario || 'DEFAULT'}</span></td>
                                <td>${a.http_status || '—'}</td>
                                <td><span class="status-badge badge-${a.status}">${a.status}</span></td>
                                <td>${a.timestamp}</td>
                            </tr>
                        `;
                    });
                    attemptsHtml += '</tbody></table>';
                } else {
                    attemptsHtml += '<p style="color: var(--text-muted);">No attempts made yet.</p>';
                }
                attemptsHtml += '</div>';

                document.getElementById('modalBody').innerHTML = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; font-size: 13px;">
                        <div>
                            <div><strong>Client Request:</strong> ${p.client_request_id}</div>
                            <div><strong>Customer:</strong> ${p.customer_name}</div>
                            <div><strong>Plan:</strong> ${p.plan_code} | ₹${p.sum_insured}</div>
                        </div>
                        <div>
                            <div><strong>Status:</strong> <span class="status-badge badge-${p.status}">${p.status}</span></div>
                            <div><strong>Policy Number:</strong> ${p.policy_number || 'N/A'}</div>
                            <div><strong>Provider Ref:</strong> ${p.provider_reference || 'N/A'}</div>
                        </div>
                    </div>
                    <h4 style="margin-bottom: 8px;">State Transition History</h4>
                    ${historyHtml}
                    ${attemptsHtml}
                `;
            } catch (err) {
                document.getElementById('modalBody').innerHTML = `<p style="color: var(--danger);">Failed to load audit logs: ${err.message}</p>`;
            }
        }

        function closeModal(e) {
            document.getElementById('auditModal').style.display = 'none';
        }

        function setPreset(type) {
            if (type === 'duplicate') {
                document.getElementById('client_request_id').value = 'AGT-2026-0001';
                document.getElementById('agent_id').value = 'AGENT-17';
            } else {
                const rand = Math.floor(1000 + Math.random() * 9000);
                document.getElementById('client_request_id').value = 'AGT-2026-' + rand;
                document.getElementById('customer_name').value = 'Traveler ' + rand;
            }
            updateLiveCalculation();
        }
    </script>
</body>
</html>
