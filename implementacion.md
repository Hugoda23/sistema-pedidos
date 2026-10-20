# Sistema de Pedidos — plan de implementación

## Decisiones del cliente (2026-10-19)
1. El **proveedor NO ve** el nombre del cliente final.
2. No hay corte fijo de 3 días: el **proveedor cierra el pedido consolidado** cuando acuerdan
   (p. ej. "hay pedido para mañana"). Lo que se agregue después cae en el siguiente.
3. Solo **precio de venta**, invisible para el proveedor. El cliente deja **anticipo** para hacer su pedido.
4. Avisos por **WhatsApp (whatsapp-web.js)**.
5. Solo entran **Administrador, Vendedores y Proveedor**. El cliente es un registro, sin login.

## Modelo
- `clientes`: nombre, teléfono, moto, notas.
- `productos`: código (único), nombre; se llena solo al pedir.
- `pedidos` (del cliente): número, cliente, vendedor, anticipo, total, saldo, estado, notas.
- `pedido_items`: pedido, producto/código, descripción, cantidad, precio venta, estado,
  fecha aprox. de ingreso, lote_id, historial.
- `lotes` (pedido consolidado al proveedor): número, estado abierto/cerrado, cerrado_por, cerrado_en.
- Estados de línea: pendiente → enviado → recibido → entregado; o no_disponible (con fecha aprox.)
  → de vuelta a pendiente/enviado; o descontinuado. Anulado si se anula el pedido.

## Pasos (un commit por paso, pruebas en verde)
1. [x] Docker + Laravel + React + login (Sanctum por token, roles base).
2. [ ] Usuarios, roles y permisos (CRUD; spatie/permission).
3. [ ] Clientes y productos.
4. [ ] Pedidos de clientes con varias líneas y anticipo.
5. [ ] Tabla del proveedor (sin cliente ni precio) + estados + cierre de lote.
6. [ ] Fecha aprox. de ingreso → tarea diaria + servicio WhatsApp + campanita.
7. [ ] Exportar a Excel, historial de cambios.
8. [ ] Panel de inicio, datos de demostración, pulido.
