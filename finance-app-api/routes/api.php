<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// WhatsApp Webhook (no auth — secured by bridge_secret)
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);

// Auth routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes (Sanctum auth)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/verify-phone', [AuthController::class, 'verifyPhone']);
    Route::post('/auth/request-verification', [AuthController::class, 'requestPhoneVerification']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/profile', [AuthController::class, 'profile']);

    // Transactions
    Route::apiResource('transactions', TransactionController::class)->except(['show']);

    // Categories
    Route::apiResource('categories', CategoryController::class)->except(['show']);

    // Wallets
    Route::apiResource('wallets', WalletController::class)->except(['show', 'destroy']);

    // Analytics
    Route::prefix('analytics')->group(function () {
        Route::get('/summary', [AnalyticsController::class, 'summary']);
        Route::get('/trends', [AnalyticsController::class, 'trends']);
        Route::get('/category-breakdown', [AnalyticsController::class, 'categoryBreakdown']);
        Route::get('/balance-prediction', [AnalyticsController::class, 'balancePrediction']);
    });

    // Budgets
    Route::apiResource('budgets', BudgetController::class)->except(['show']);
});
