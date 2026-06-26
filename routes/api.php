<?php

use App\Http\Controllers\Api\AnalyzeTicketController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::post('/analyze-ticket', AnalyzeTicketController::class);
