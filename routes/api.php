<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DemoLoginController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentSummaryController;
use App\Http\Controllers\Api\DraftController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UsageController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('auth')->group(function () {
    Route::post('/register', [RegisterController::class, 'store']);
    Route::post('/login', [LoginController::class, 'store']);
    Route::post('/logout', [LogoutController::class, 'destroy'])->middleware('auth:sanctum');
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot']);
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);
});

Route::post('/demo-login', DemoLoginController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);
    Route::patch('/me', [MeController::class, 'update']);
    Route::delete('/me', [MeController::class, 'destroy']);

    Route::patch('/settings', [SettingsController::class, 'update']);
    Route::post('/settings/test-connection', [SettingsController::class, 'testConnection']);

    Route::middleware('throttle:upload')->group(function () {
        Route::post('/documents', [DocumentController::class, 'store']);
    });
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::get('/documents/{document}', [DocumentController::class, 'show']);
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
    Route::get('/documents/{document}/file', [DocumentController::class, 'file']);

    Route::get('/documents/{document}/summary', [DocumentSummaryController::class, 'show']);
    Route::middleware(['token.budget', 'throttle:summary'])->group(function () {
        Route::post('/documents/{document}/summary', [DocumentSummaryController::class, 'store']);
    });

    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::patch('/conversations/{conversation}', [ConversationController::class, 'update']);
    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy']);

    Route::middleware(['token.budget', 'throttle:chat'])->group(function () {
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);
        Route::post('/conversations/{conversation}/messages/{message}/regenerate', [MessageController::class, 'regenerate']);
    });

    Route::get('/drafts', [DraftController::class, 'index']);
    Route::get('/drafts/{draft}', [DraftController::class, 'show']);
    Route::delete('/drafts/{draft}', [DraftController::class, 'destroy']);
    Route::middleware(['token.budget', 'throttle:draft'])->group(function () {
        Route::post('/drafts', [DraftController::class, 'store']);
        Route::post('/drafts/{draft}/iterate', [DraftController::class, 'iterate']);
    });

    Route::get('/usage/today', [UsageController::class, 'today']);
    Route::get('/usage/timeseries', [UsageController::class, 'timeseries']);
    Route::get('/usage/breakdown', [UsageController::class, 'breakdown']);
});
