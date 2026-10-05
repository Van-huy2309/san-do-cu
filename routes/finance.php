<?php

use App\Http\Controllers\Internal\FinanceApiController;
use Illuminate\Support\Facades\Route;

Route::post('/bank/has', [FinanceApiController::class, 'hasBank']);
Route::post('/bank/show', [FinanceApiController::class, 'showBank']);
Route::post('/bank/save', [FinanceApiController::class, 'saveBank']);
Route::post('/ledger/receive', [FinanceApiController::class, 'receive']);
Route::post('/ledger/refund', [FinanceApiController::class, 'refund']);
Route::post('/ledger/settle', [FinanceApiController::class, 'settle']);
Route::post('/ledger/seller', [FinanceApiController::class, 'seller']);
Route::post('/ledger/admin', [FinanceApiController::class, 'admin']);
