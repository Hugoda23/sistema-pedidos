<?php

// Datos que salen en el encabezado del comprobante del pedido.
return [
    'nombre' => env('NEGOCIO_NOMBRE', 'Repuestos para Moto'),
    'telefono' => env('NEGOCIO_TELEFONO'),
    'direccion' => env('NEGOCIO_DIRECCION'),
    'nit' => env('NEGOCIO_NIT'),
];
