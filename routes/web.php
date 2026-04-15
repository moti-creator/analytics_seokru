<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReportController;

Route::get('/', [ReportController::class, 'landing']);
Route::get('/start/{type}', [ReportController::class, 'start'])->name('start');

Route::get('/auth/google', [AuthController::class, 'redirect']);
Route::get('/auth/google/callback', [AuthController::class, 'callback']);

Route::get('/connect', [ReportController::class, 'connectForm'])->name('connect');
Route::post('/generate', [ReportController::class, 'generate'])->name('generate');
Route::get('/report/{id}', [ReportController::class, 'show'])->name('report.show');
Route::get('/report/{id}/pdf', [ReportController::class, 'pdf'])->name('report.pdf');
