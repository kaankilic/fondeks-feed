<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/', fn () => redirect('/admin'));

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('/founders', [Admin\FounderController::class, 'index'])->name('founders');
    Route::get('/funds', [Admin\FundController::class, 'index'])->name('funds');
    Route::get('/symbols', [Admin\SymbolController::class, 'index'])->name('symbols');
    Route::get('/fund-daily-stats', [Admin\FundDailyStatController::class, 'index'])->name('fund-daily-stats');
    Route::get('/fund-positions', [Admin\FundPositionController::class, 'index'])->name('fund-positions');
    Route::get('/fund-allocations', [Admin\FundAllocationController::class, 'index'])->name('fund-allocations');
    Route::get('/fund-similarities', [Admin\FundSimilarityController::class, 'index'])->name('fund-similarities');
    Route::get('/fund-holding-snapshots', [Admin\FundHoldingSnapshotController::class, 'index'])->name('fund-holding-snapshots');
    Route::get('/fund-disclosures', [Admin\FundDisclosureController::class, 'index'])->name('fund-disclosures');
    Route::get('/market-indices', [Admin\MarketIndexController::class, 'index'])->name('market-indices');
    Route::get('/index-quotes', [Admin\IndexQuoteController::class, 'index'])->name('index-quotes');
    Route::get('/kap-portfolio-reports', [Admin\KapPortfolioReportController::class, 'index'])->name('kap-portfolio-reports');
    Route::get('/kap-extraction-batches', [Admin\KapExtractionBatchController::class, 'index'])->name('kap-extraction-batches');
    Route::get('/category-performance', [Admin\CategoryPerformanceController::class, 'index'])->name('category-performance');
    Route::get('/news', [Admin\NewsController::class, 'index'])->name('news');
    Route::get('/guides', [Admin\GuideController::class, 'index'])->name('guides');
    Route::get('/ingest-runs', [Admin\IngestRunController::class, 'index'])->name('ingest-runs');
    Route::get('/ingest', [Admin\IngestController::class, 'index'])->name('ingest');
    Route::post('/ingest/run', [Admin\IngestController::class, 'run'])->name('ingest.run');
    Route::get('/users', [Admin\UserController::class, 'index'])->name('users');
});
