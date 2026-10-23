<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\UserController;
use App\Models\Category;
use Illuminate\Support\Facades\Route;

// Contrato §3. El prefijo /api/v1 se define en bootstrap/app.php.

// Públicos (CA-01.3: lo único sin token)
Route::get('/health', fn () => ['status' => 'ok']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // §3.2 Usuarios
    Route::post('/users', [UserController::class, 'store'])->middleware('role:admin');

    // §3.3 Categorías: cualquier autenticado, orden por nombre
    Route::get('/categories', fn () => Category::orderBy('name')->get());

    // §3.4 Libros: consultar cualquier autenticado; modificar solo admin
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'destroy']);
    });

    // §3.5 Socios: admin y librarian
    Route::middleware('role:admin,librarian')->group(function () {
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/members/{id}', [MemberController::class, 'show']);
        Route::post('/members', [MemberController::class, 'store']);
    });
});