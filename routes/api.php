<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Autenticación — rutas públicas (sin auth:sanctum)
Route::post('/login', [AuthController::class, 'login']);

// Autenticación — requiere token válido
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn (Request $r) => $r->user());
});

// User management — autorización delegada al pipeline CoR en cada controller action
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class);
});
