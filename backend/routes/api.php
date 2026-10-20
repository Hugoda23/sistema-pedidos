<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\NotificacionController;
use App\Http\Controllers\Api\PanelController;
use App\Http\Controllers\Api\PedidoController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\ProveedorController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\WhatsAppController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', 'activo'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/perfil', [AuthController::class, 'actualizarPerfil']);
    Route::get('/panel', PanelController::class);

    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::post('/notificaciones/leer', [NotificacionController::class, 'leer']);

    // Cada usuario vincula su propio WhatsApp; los avisos salen desde ahí.
    Route::get('/whatsapp', [WhatsAppController::class, 'estado']);
    Route::post('/whatsapp/conectar', [WhatsAppController::class, 'conectar'])->middleware('throttle:10,1');
    Route::post('/whatsapp/desconectar', [WhatsAppController::class, 'desconectar']);
    Route::get('/whatsapp/mensajes', [WhatsAppController::class, 'mensajes']);
    Route::post('/whatsapp/mensajes/{mensaje}/reintentar', [WhatsAppController::class, 'reintentar']);
    Route::get('/whatsapp/sesiones', [WhatsAppController::class, 'sesiones'])->middleware('permission:usuarios.ver');

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

    Route::middleware('permission:pedidos.ver')->group(function () {
        Route::get('/pedidos', [PedidoController::class, 'index']);
        Route::get('/pedidos/exportar', [PedidoController::class, 'exportar']);
        Route::get('/pedidos/{pedido}', [PedidoController::class, 'show']);
        Route::get('/pedidos/{pedido}/comprobante', [PedidoController::class, 'comprobante']);
        Route::post('/pedidos', [PedidoController::class, 'store'])->middleware('permission:pedidos.gestionar');
        Route::put('/pedidos/{pedido}', [PedidoController::class, 'update']);
        Route::post('/pedidos/{pedido}/items', [PedidoController::class, 'agregarItem']);
        Route::put('/pedido-items/{item}', [PedidoController::class, 'actualizarItem']);
        Route::delete('/pedido-items/{item}', [PedidoController::class, 'eliminarItem']);
        Route::post('/pedido-items/{item}/estado', [PedidoController::class, 'estadoItem']);
        Route::post('/pedidos/{pedido}/pagos', [PedidoController::class, 'agregarPago']);
        Route::delete('/pagos/{pago}', [PedidoController::class, 'eliminarPago']);
        Route::post('/pedidos/{pedido}/whatsapp', [WhatsAppController::class, 'alCliente'])->middleware('throttle:20,1');
        Route::post('/pedido-items/{item}/preguntar', [PedidoController::class, 'preguntarProveedor'])->middleware('throttle:20,1');
        Route::post('/pedidos/{pedido}/anular', [PedidoController::class, 'anular'])->middleware('permission:pedidos.anular');
    });

    Route::middleware('permission:proveedor.ver')->prefix('proveedor')->group(function () {
        Route::get('/items', [ProveedorController::class, 'items']);
        Route::get('/consolidado', [ProveedorController::class, 'consolidado']);
        Route::get('/exportar', [ProveedorController::class, 'exportar']);
        Route::get('/lotes', [ProveedorController::class, 'lotes']);
        Route::post('/items/estado', [ProveedorController::class, 'cambiarEstado'])->middleware('permission:proveedor.actualizar');
        Route::post('/lotes/cerrar', [ProveedorController::class, 'cerrarLote'])->middleware('permission:proveedor.cerrar_lote');
    });
});
