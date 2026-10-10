<?php

use App\Http\Controllers\ByeCode\AccountController;
use App\Http\Controllers\ByeCode\CatalogController;
use App\Http\Controllers\ByeCode\CommerceController;
use App\Http\Controllers\ByeCode\ImportController;
use App\Http\Controllers\ByeCode\IntelligenceController;
use App\Http\Controllers\ByeCode\PortalController;
use App\Http\Controllers\ByeCode\PredictionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('home');
Route::get('/catalog', [CatalogController::class, 'index']);
Route::get('/catalog/{style}', [CatalogController::class, 'show'])->whereNumber('style');
Route::post('/selection/save', [CatalogController::class, 'saveSelection'])->middleware('throttle:120,1')->block();
Route::post('/selection', [CatalogController::class, 'selection'])->middleware('throttle:30,1')->block();
require __DIR__.'/journey.php';

Route::get('dashboard', [AccountController::class, 'home'])->middleware('auth')->name('dashboard');
Route::middleware(['auth', 'role:commerce'])->group(function () {
    Route::get('workspace', [CommerceController::class, 'dashboard']);
    Route::get('cases', [CommerceController::class, 'index'])->name('cases.index');
    Route::get('quotes/create', [CommerceController::class, 'quoteForm'])->name('quotes.create');
    Route::post('quotes', [CommerceController::class, 'storeQuote'])->middleware('throttle:30,1');
    Route::get('cases/{case}', [CommerceController::class, 'show'])->name('cases.show');
    Route::get('cases/{case}/quote', [CommerceController::class, 'quoteForm']);
    Route::post('cases/{case}/quote', [CommerceController::class, 'storeQuote'])->middleware('throttle:30,1');
    Route::post('cases/{case}/events', [CommerceController::class, 'event'])->middleware('throttle:30,1');
    Route::post('cases/{case}/evaluations', [CommerceController::class, 'evaluate'])->middleware('throttle:10,1');
    Route::post('cases/{case}/portal-link', [CommerceController::class, 'portalLink'])->middleware('throttle:10,1');
    Route::post('recommendations/{recommendation}/review', [CommerceController::class, 'review']);
    Route::post('recommendations/{recommendation}/feedback', [CommerceController::class, 'feedback']);
    Route::get('customers', [CommerceController::class, 'customers'])->name('customers.index');
    Route::get('products', [CatalogController::class, 'manage'])->name('products.index');
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('imports', [ImportController::class, 'store'])->middleware('throttle:10,1');
    Route::post('imports/kit', [ImportController::class, 'kit'])->middleware('throttle:5,1');
    Route::post('imports/{dataSet}/update', [ImportController::class, 'updateKit']);
    Route::post('imports/{dataSet}/evaluate', [ImportController::class, 'evaluate'])->middleware('throttle:5,1');
    Route::get('exports/{dataSet}/recommendations', [ImportController::class, 'export']);
    Route::get('intelligence', [IntelligenceController::class, 'index'])->name('intelligence.index');
    Route::get('prediction', [PredictionController::class, 'index'])->name('prediction.index');
    Route::post('prediction/train', [PredictionController::class, 'train'])->middleware('throttle:3,1');
    Route::post('prediction/history', [PredictionController::class, 'history'])->middleware('throttle:3,1');
    Route::get('prediction/template', [PredictionController::class, 'template']);
});

Route::get('portal/{token}', [PortalController::class, 'show'])->middleware(['auth', 'throttle:60,1']);

require __DIR__.'/settings.php';
