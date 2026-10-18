<?php

use Illuminate\Support\Facades\Route;

// Holding page until locale routing arrives in Phase 1, step 1.3.
Route::view('/', 'pages.holding')->name('home');
