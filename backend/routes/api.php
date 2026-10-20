<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', 'activo'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/usuarios', [UsuarioController::class, 'index'])->middleware('permission:usuarios.ver');
    Route::get('/usuarios/{usuario}', [UsuarioController::class, 'show'])->middleware('permission:usuarios.ver');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->middleware('permission:usuarios.gestionar');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->middleware('permission:usuarios.gestionar');

    // El listado de roles también lo usa el formulario de usuarios.
    Route::get('/roles', [RolController::class, 'index'])->middleware('permission:roles.ver|usuarios.gestionar');
    Route::post('/roles', [RolController::class, 'store'])->middleware('permission:roles.gestionar');
    Route::put('/roles/{rol}', [RolController::class, 'update'])->middleware('permission:roles.gestionar');
    Route::delete('/roles/{rol}', [RolController::class, 'destroy'])->middleware('permission:roles.gestionar');
});
