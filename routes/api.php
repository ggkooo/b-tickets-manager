<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/tickets', [TicketController::class, 'index']);
Route::post('/tickets', [TicketController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/tickets/{id}/call', [TicketController::class, 'call']);
    Route::post('/tickets/{id}/recall', [TicketController::class, 'recall']);
    Route::patch('/tickets/{id}/complete', [TicketController::class, 'complete']);
    Route::patch('/tickets/{id}/cancel', [TicketController::class, 'cancel']);
    Route::get('/tickets/completed', [TicketController::class, 'completed']);
});

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/reports/attendances', [ReportController::class, 'attendances']);
    Route::post('/videos/upload', [VideoController::class, 'upload']);
    Route::delete('/videos/{filename}', [VideoController::class, 'destroy']);
    Route::get('/users', [UserController::class, 'index']);
    Route::patch('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::patch('/users/{user}/make-admin', [UserController::class, 'makeAdmin']);
    Route::patch('/users/{user}/remove-admin', [UserController::class, 'removeAdmin']);
});

Route::get('/tickets/recently-called', [TicketController::class, 'recentlyCalled']);
Route::get('/videos/{filename}', [VideoController::class, 'show']);
Route::get('/videos', [VideoController::class, 'index']);
