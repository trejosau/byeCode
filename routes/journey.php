<?php

use App\Http\Controllers\ByeCode\AccountController;
use App\Http\Controllers\ByeCode\CatalogController;
use App\Http\Controllers\ByeCode\ConversationController;
use App\Http\Controllers\ByeCode\DraftController;
use App\Http\Controllers\ByeCode\ProfileController;
use App\Http\Controllers\ByeCode\RequestController;
use App\Http\Controllers\ByeCode\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::post('/activity', [TelemetryController::class, 'store'])->middleware('throttle:60,1');
Route::middleware('auth')->group(function () {
    Route::get('/initial-password', [AccountController::class, 'password']);
    Route::post('/initial-password', [AccountController::class, 'changePassword'])->middleware('throttle:6,1');
});
Route::middleware('auth')->group(function () {
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin', [AccountController::class, 'admin']);
        Route::post('/admin/team', [AccountController::class, 'team'])->middleware('throttle:10,1');
    });
    Route::middleware('role:customer')->group(function () {
        Route::get('/request', [RequestController::class, 'form']);
        Route::post('/request', [RequestController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/my/requests', [RequestController::class, 'index']);
        Route::post('/journey/{case}/decision', [ConversationController::class, 'decision'])->middleware('throttle:10,1');
    });
    Route::middleware('role:commerce')->group(function () {
        Route::post('/cases/{case}/draft', [DraftController::class, 'store']);
        Route::get('/requests', [RequestController::class, 'index']);
        Route::get('/physical-request', [RequestController::class, 'form']);
        Route::post('/physical-request', [RequestController::class, 'store'])->middleware('throttle:10,1');
        Route::post('/requests/{case}/assign', [RequestController::class, 'assign']);
        Route::post('/products/styles', [CatalogController::class, 'style']);
        Route::post('/products/variants', [CatalogController::class, 'variant']);
        Route::post('/journey/{case}/result', [ConversationController::class, 'result']);
        Route::post('/journey/{case}/withdraw', [ConversationController::class, 'withdraw']);
        Route::post('/customers/{customer}/facts', [ProfileController::class, 'correct']);
        Route::post('/customers/{customer}/analyze', [ProfileController::class, 'analyze'])->middleware('throttle:3,1');
    });
    Route::get('/customers/{customer}', [ProfileController::class, 'show'])->middleware('role:commerce,admin');
    Route::get('/my/quotes/{publicId}', [ConversationController::class, 'show']);
    Route::get('/attachments/{id}', [ConversationController::class, 'attachment']);
    Route::post('/journey/{case}/messages', [ConversationController::class, 'message'])->middleware(['role:commerce,customer', 'throttle:30,1']);
});
