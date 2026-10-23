<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReportController;
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

    // §3.3 Categorías
    Route::get('/categories', fn () => Category::orderBy('name')->get());

    // §3.4 Libros
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'destroy']);
    });

    Route::middleware('role:admin,librarian')->group(function () {
        // §3.5 Socios
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/members/{id}', [MemberController::class, 'show']);
        Route::post('/members', [MemberController::class, 'store']);

        // §3.6 Préstamos. /loans/overdue va antes de /loans/{id}.
        Route::get('/loans/overdue', [LoanController::class, 'overdue']);
        Route::get('/loans', [LoanController::class, 'index']);
        Route::post('/loans', [LoanController::class, 'store']);
        Route::get('/loans/{id}', [LoanController::class, 'show']);
        Route::post('/loans/{loanId}/items/{itemId}/return', [LoanController::class, 'returnItem']);
    });

    // §3.7 Reporte: solo admin
    Route::get('/reports/most-borrowed', [ReportController::class, 'mostBorrowed'])->middleware('role:admin');
});
