<?php

// Catálogo único de permisos y roles base. El seeder lo sincroniza;
// los roles se pueden ajustar después desde el módulo de Roles.
return [
    'permisos' => [
        'usuarios.ver' => 'Ver usuarios',
        'usuarios.gestionar' => 'Crear y editar usuarios',
        'roles.ver' => 'Ver roles y permisos',
        'roles.gestionar' => 'Crear y editar roles',
        'clientes.ver' => 'Ver clientes',
        'clientes.gestionar' => 'Crear y editar clientes',
        'pedidos.ver' => 'Ver sus pedidos',
        'pedidos.ver_todos' => 'Ver pedidos de todos los vendedores',
        'pedidos.gestionar' => 'Crear y editar pedidos',
        'pedidos.anular' => 'Anular pedidos',
        'inventario.ver' => 'Ver el pedido de inventario',
        'inventario.gestionar' => 'Agregar repuestos al pedido de inventario',
        'inventario.atender' => 'Marcar enviado / no disponible en inventario',
        'proveedor.ver' => 'Ver repuestos solicitados al proveedor',
        'proveedor.actualizar' => 'Marcar enviado / no disponible / descontinuado',
        'proveedor.cerrar_lote' => 'Cerrar pedido consolidado',
        'reportes.ver' => 'Ver panel y reportes',
    ],

    'roles' => [
        'Administrador' => '*',
        'Vendedor' => [
            'clientes.ver', 'clientes.gestionar', 'inventario.ver', 'inventario.gestionar',
            'pedidos.ver', 'pedidos.gestionar', 'reportes.ver',
        ],
        'Proveedor' => [
            'proveedor.ver', 'proveedor.actualizar', 'proveedor.cerrar_lote', 'inventario.ver', 'inventario.atender',
        ],
    ],
];
