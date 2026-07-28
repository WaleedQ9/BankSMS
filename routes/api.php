<?php

use App\Http\Controllers\Api\SmsController;
use App\Http\Controllers\Api\TelegramController;
use App\Http\Middleware\ApiKeyMiddleware;
use Illuminate\Support\Facades\Route;

Route::post('/sms/receive', [SmsController::class, 'receive'])
    ->middleware(ApiKeyMiddleware::class);

Route::post('/telegram/webhook', [TelegramController::class, 'webhook']);
