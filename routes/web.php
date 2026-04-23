<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AskController;
use App\Http\Controllers\TelegramWebhookController;

Route::get('/', [ReportController::class, 'landing']);

// Legal pages (required for Google OAuth verification)
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/about', 'legal.about')->name('about');
Route::get('/start/{type}', [ReportController::class, 'start'])->name('start');
Route::post('/ask/start', [ReportController::class, 'askStart'])->name('ask.start');

Route::get('/auth/google', [AuthController::class, 'redirect']);
Route::get('/auth/google/callback', [AuthController::class, 'callback']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/connect', [ReportController::class, 'connectForm'])->name('connect');
Route::post('/generate', [ReportController::class, 'generate'])->name('generate');

// Dashboard (property first, then pick report)
Route::get('/dashboard', [ReportController::class, 'dashboard'])->name('dashboard');
Route::post('/dashboard/property', [ReportController::class, 'updateProperty'])->name('dashboard.property');
Route::get('/generate/{type}', [ReportController::class, 'generateDirect'])->name('generate.direct');

Route::get('/ask', [AskController::class, 'form'])->name('ask.form');
Route::post('/ask', [AskController::class, 'run'])->name('ask.run');
Route::post('/ask/saved', [AskController::class, 'saveQuery'])->name('ask.save');
Route::delete('/ask/saved/{saved}', [AskController::class, 'deleteSaved'])->name('ask.saved.delete');

Route::get('/r/{report:slug}', [ReportController::class, 'show'])->name('report.show');
Route::get('/r/{report:slug}/pdf', [ReportController::class, 'pdf'])->name('report.pdf');

// Telegram bot webhook — CSRF exempted via VerifyCsrfToken::$except
Route::post('/webhooks/telegram', [TelegramWebhookController::class, 'handle'])->name('webhook.telegram');
