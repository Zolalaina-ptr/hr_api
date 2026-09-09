<?php

use Illuminate\Support\Facades\Route;

// Keep the web route file available for future browser-facing endpoints.
Route::redirect('/', '/api');
