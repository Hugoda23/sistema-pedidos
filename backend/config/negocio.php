<?php

// Datos que salen en el encabezado del comprobante del pedido y de los reportes.
return [
    'nombre' => env('NEGOCIO_NOMBRE', 'Motorepuestos Mota'),
    // Logo para comprobantes y reportes (el mismo de Taller-sis).
    'logo' => resource_path('img/logo.jpg'),
    'telefono' => env('NEGOCIO_TELEFONO'),
    'direccion' => env('NEGOCIO_DIRECCION'),
    'nit' => env('NEGOCIO_NIT'),
];
