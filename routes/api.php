<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FounderController;
use App\Http\Controllers\Api\FundController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LeaderController;
use App\Http\Controllers\Api\SymbolController;
use Illuminate\Support\Facades\Route;

/**
 * The public JSON API. These routes are registered twice by bootstrap/app.php:
 * under the `/api` prefix for local, same-origin use, and at the root of the
 * dedicated API subdomain (api.fondeks.com) in production. The paths here carry
 * no prefix of their own so both mounts stay in sync.
 */

// ── Public market data (no auth — matches the client's edge-cached routes) ───
Route::get('/health', [HealthController::class, 'show']);

Route::get('/funds', [FundController::class, 'index']);
Route::get('/funds/search', [FundController::class, 'search']);
Route::get('/funds/{code}', [FundController::class, 'show']);

Route::get('/leaders', [LeaderController::class, 'index']);

// Proxied BIST ticker logo (Forinvest CDN), served from our own origin.
Route::get('/symbols/{code}/icon', [SymbolController::class, 'icon']);

// Proxied fund founder (kurucu) logo (Fintables storage), served from our own origin.
Route::get('/founders/{slug}/icon', [FounderController::class, 'icon']);

// ── Sanctum-protected surface ────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
