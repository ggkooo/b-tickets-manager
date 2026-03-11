<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\ReportController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/tickets', [TicketController::class, 'index']);
Route::post('/tickets', [TicketController::class, 'store']);
Route::patch('/tickets/{id}/complete', [TicketController::class, 'complete']);
Route::get('/tickets/completed', [TicketController::class, 'completed']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/tickets/{id}/call', [TicketController::class, 'call']);
});

Route::get('/tickets/recently-called', [TicketController::class, 'recentlyCalled']);
Route::get('/reports/attendances', [ReportController::class, 'attendances']);

Route::post('/videos/upload', [VideoController::class, 'upload']);
Route::get('/videos/{filename}', [VideoController::class, 'show']);
Route::get('/videos', [VideoController::class, 'index']);
