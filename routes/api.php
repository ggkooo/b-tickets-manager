<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TicketController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/tickets', [TicketController::class, 'index']);
Route::post('/tickets', [TicketController::class, 'store']);
Route::patch('/tickets/{id}/complete', [TicketController::class, 'complete']);

// Routes that require a valid Bearer token (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/tickets/{id}/call', [TicketController::class, 'call']);
});

// Rota pública para retornar as últimas 5 senhas chamadas
Route::get('/tickets/recently-called', [TicketController::class, 'recentlyCalled']);
