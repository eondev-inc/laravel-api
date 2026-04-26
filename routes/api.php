<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Autenticación — rutas públicas (sin auth:sanctum)
Route::post('/login', [AuthController::class, 'login']);

// Autenticación — requiere token válido
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/user', fn (Request $r) => $r->user());
});

// User management — autorización delegada al pipeline CoR en cada controller action
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class);
});

// Catalog — GET público, mutaciones protegidas por CoR dentro de cada controller
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/designs', [DesignController::class, 'index']);
Route::get('/designs/{design}', [DesignController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
    Route::apiResource('products', ProductController::class)->except(['index', 'show']);
    Route::middleware('throttle:uploads')->group(function () {
        Route::post('/designs', [DesignController::class, 'store']);
    });
    Route::delete('/designs/{design}', [DesignController::class, 'destroy']);
});

// Cart — public (guest + auth), session via X-Cart-Session or Bearer token
Route::get('/cart', [CartController::class, 'show']);
Route::post('/cart/items', [CartController::class, 'addItem']);
Route::put('/cart/items/{cartItem}', [CartController::class, 'updateItem']);
Route::delete('/cart/items/{cartItem}', [CartController::class, 'removeItem']);

// Cart merge — requires authenticated user
Route::middleware('auth:sanctum')->post('/cart/merge', [CartController::class, 'mergeCart']);

// Checkout — create requires auth, commit is public callback from Transbank
Route::middleware('auth:sanctum')->post('/checkout', [CheckoutController::class, 'create']);
Route::match(['get', 'post'], '/checkout/commit', [CheckoutController::class, 'commit'])->name('checkout.commit');
