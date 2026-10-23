<?php

namespace App\Http\Controllers;

use App\Models\PolicyRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $stats = [
            'total' => PolicyRequest::count(),
            'issued' => PolicyRequest::where('status', PolicyRequest::STATUS_ISSUED)->count(),
            'pending' => PolicyRequest::where('status', PolicyRequest::STATUS_PENDING_CONFIRMATION)->count(),
            'reconcile' => PolicyRequest::where('status', PolicyRequest::STATUS_NEEDS_RECONCILIATION)->count(),
            'failed' => PolicyRequest::where('status', PolicyRequest::STATUS_FAILED)->count(),
            'validated' => PolicyRequest::where('status', PolicyRequest::STATUS_VALIDATED)->count(),
        ];

        $policies = PolicyRequest::with(['stateHistory', 'attempts'])
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        return view('dashboard', compact('stats', 'policies'));
    }
}
