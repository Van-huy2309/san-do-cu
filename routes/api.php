<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ListingController;
use Illuminate\Support\Facades\Route;

Route::get('/listings', [ListingController::class, 'index']);
Route::get('/listings/{listing:slug}', [ListingController::class, 'show']);
Route::get('/categories', function () {
    return ['data' => \App\Models\Category::activeCached()->map(fn ($c) => [
        'id' => $c->id, 'name' => $c->name, 'icon' => $c->icon,
    ])];
});

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:8,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:8,1');
Route::post('/auth/otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:5,1');
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:8,1');
Route::post('/auth/google', [AuthController::class, 'google'])->middleware('throttle:8,1');
Route::post('/auth/apple', [AuthController::class, 'apple'])->middleware('throttle:8,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/favorites', [AccountController::class, 'favorites']);
    Route::post('/listings/{listing:slug}/favorite', [AccountController::class, 'toggleFavorite']);
    Route::get('/conversations', [AccountController::class, 'conversations']);
    Route::get('/conversations/{conversation}', [AccountController::class, 'messages']);
    Route::post('/conversations/{conversation}', [AccountController::class, 'reply']);
    Route::post('/listings/{listing:slug}/chat', [AccountController::class, 'startChat']);
});
