<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Contrato §3. El prefijo /api/v1 se define en bootstrap/app.php.

// Públicos (CA-01.3: lo único sin token)
Route::get('/health', fn () => ['status' => 'ok']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Con token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/users', [UserController::class, 'store'])->middleware('role:admin');
});