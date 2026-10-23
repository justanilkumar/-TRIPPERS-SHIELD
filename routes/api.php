<?php

use App\Http\Controllers\MockInsurerController;
use App\Http\Controllers\PolicyRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PolicyFlow API Routes
|--------------------------------------------------------------------------
|
| Core API endpoints for policy intake, issuance, mock external insurer
| reconciliation, and idempotent callbacks.
|
*/

// Policy Request Intake & Queries
Route::post('/policy-requests', [PolicyRequestController::class, 'store']);
Route::get('/policy-requests', [PolicyRequestController::class, 'index']);
Route::get('/policy-requests/{id}', [PolicyRequestController::class, 'show']);

// Issuance & Reconciliation Actions
Route::post('/policy-requests/{id}/submit', [PolicyRequestController::class, 'submit']);
Route::post('/policy-requests/{id}/reconcile', [PolicyRequestController::class, 'reconcile']);

// Mock Insurer Callbacks & Provider Status
Route::post('/mock-insurer/callback', [MockInsurerController::class, 'handleCallback']);
Route::get('/mock-insurer/status/{reference}', [MockInsurerController::class, 'checkStatus']);
Route::post('/mock-insurer/dispatch-callback', [MockInsurerController::class, 'triggerSimulatedCallback']);
