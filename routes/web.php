<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('welcome'))->name('home');
Route::get('dashboard', fn () => Inertia::render('dashboard'))
    ->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
