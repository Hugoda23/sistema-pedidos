# Sistema de Pedidos — plan de implementación

## Decisiones del cliente (2026-10-19)
1. El **proveedor NO ve** el nombre del cliente final.
2. No hay corte fijo de 3 días: el **proveedor cierra el pedido consolidado** cuando acuerdan
   (p. ej. "hay pedido para mañana"). Lo que se agregue después cae en el siguiente.
3. Solo **precio de venta**, invisible para el proveedor. El cliente deja **anticipo** para hacer su pedido.
4. Avisos por WhatsApp con whatsapp-web.js: cada usuario vincula SU número (QR en «Mi WhatsApp») y
   el mensaje sale solo desde el número de quien actúa. (Hubo un intento intermedio con enlaces wa.me;
   el cliente pidió volver a las sesiones, 2026-10-19.) Al cliente se le puede adjuntar el PDF del pedido.
5. Solo entran **Administrador, Vendedores y Proveedor**. El cliente es un registro, sin login.

## Modelo
- `clientes`: nombre, teléfono, moto, notas.
- Sin catálogo de productos (quitado 2026-10-19 a pedido del cliente): cada línea guarda su código y nombre.
- `pedidos` (del cliente): número, cliente, vendedor, total, pagado, estado, notas, anulación.
- `pagos`: anticipo (el primero) y abonos; saldo = total − pagado.
- `pedido_items`: pedido, producto/código, descripción, cantidad, precio venta, estado,
  fecha aprox. de ingreso, lote_id, historial.
- `lotes` (pedido consolidado al proveedor): número, estado abierto/cerrado, cerrado_por, cerrado_en.
- Estados de línea: pendiente → enviado → recibido → entregado; o no_disponible (con fecha aprox.)
  → de vuelta a pendiente/enviado; o descontinuado. Anulado si se anula el pedido.

## Pasos (un commit por paso, pruebas en verde)
1. [x] Docker + Laravel + React + login (Sanctum por token, roles base).
2. [x] Usuarios, roles y permisos (CRUD; spatie/permission).
3. [x] Clientes (el catálogo de productos se quitó después).
4. [x] Pedidos de clientes con varias líneas, anticipo y abonos (tabla `pagos`).
5. [x] Tabla del proveedor (sin cliente ni precio) + estados + cierre de lote.
6. [x] Fecha aprox. de ingreso → tarea diaria (08:00) + campanita + WhatsApp por usuario (whatsapp-web.js).
7. [x] Exportar a Excel (pedidos + líneas; proveedor detalle + sumado, sin cliente ni precio). Historial ya estaba (paso 4).
8. [x] Panel de inicio por rol, «Mi perfil» (teléfono y contraseña), DemoSeeder con estados variados.
9. [x] Pedido de inventario (lista compartida, alerta de código repetido, mismo lote que los pedidos).
10. [ ] Despliegue en el VPS de Taller-sis (tras subirlo a KVM 2): compose de producción, dominio, HTTPS.
