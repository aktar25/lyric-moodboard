<?php

use App\Http\Controllers\MoodController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MoodController::class, 'index']);
Route::post('/analyze', [MoodController::class, 'analyze']);
