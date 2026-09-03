<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PrinterSettingsController;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

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
});

Route::middleware(['auth:sanctum', 'superadmin'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/printer-settings', [PrinterSettingsController::class, 'index']);
    Route::post('/printer-settings', [PrinterSettingsController::class, 'store']);
    Route::patch('/printer-settings/{printerSetting}', [PrinterSettingsController::class, 'update']);
    Route::post('/videos/upload', [VideoController::class, 'upload']);
    Route::post('/videos/link', [VideoController::class, 'storeLink']);
    Route::delete('/videos/{video}', [VideoController::class, 'destroy']);
    Route::get('/users', [UserController::class, 'index']);
    Route::patch('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::patch('/users/{user}/make-admin', [UserController::class, 'makeAdmin']);
    Route::patch('/users/{user}/remove-admin', [UserController::class, 'removeAdmin']);
});

Route::get('/tickets/recently-called', [TicketController::class, 'recentlyCalled']);
Route::get('/videos/{filename}', [VideoController::class, 'show'])->where('filename', '[A-Za-z0-9_-]+\.mp4');
Route::get('/videos', [VideoController::class, 'index']);
