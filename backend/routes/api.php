<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ProductoController;
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

    Route::get('/clientes', [ClienteController::class, 'index'])->middleware('permission:clientes.ver');
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->middleware('permission:clientes.ver');
    Route::post('/clientes', [ClienteController::class, 'store'])->middleware('permission:clientes.gestionar');
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->middleware('permission:clientes.gestionar');
    Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->middleware('permission:clientes.gestionar');

    Route::get('/productos', [ProductoController::class, 'index'])->middleware('permission:productos.ver');
    Route::get('/productos/buscar', [ProductoController::class, 'buscar'])->middleware('permission:productos.ver');
    Route::post('/productos', [ProductoController::class, 'store'])->middleware('permission:productos.gestionar');
    Route::put('/productos/{producto}', [ProductoController::class, 'update'])->middleware('permission:productos.gestionar');
    Route::delete('/productos/{producto}', [ProductoController::class, 'destroy'])->middleware('permission:productos.gestionar');
});
