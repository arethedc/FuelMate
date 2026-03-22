<?php

use App\Http\Controllers\FuelPredictorController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FuelPredictorController::class, 'index'])->name('home');
Route::post('/predict', [FuelPredictorController::class, 'predict'])->name('predict');

