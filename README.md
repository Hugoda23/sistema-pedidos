# Sistema de Pedidos

Laravel 13 (API) · React + Vite + Tailwind · PostgreSQL 16 · Docker.

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

- Frontend: http://localhost:5180
- API: http://localhost:8120/api
- Postgres: localhost:5445 (pedidos / secret)

### Datos de demostración

```bash
docker compose exec app php artisan migrate:fresh --seed --seeder=DemoSeeder
```

Simula 6 semanas de trabajo (≈60 pedidos, pagos, pedidos al proveedor cerrados cada 3 días,
sin existencia, descontinuados, notificaciones). Todos con contraseña `password`:

| Usuario | Rol | Para probar |
|---|---|---|
| `admin@pedidos.test` | Administrador | todo: usuarios, roles, anular pedidos, ve ventas y proveedor |
| `vendedor@pedidos.test` (Carlos Méndez) | Vendedor | sus pedidos y clientes |
| `maria@pedidos.test` | Vendedor | ídem |
| `jose@pedidos.test` | Vendedor | sin teléfono: el proveedor no puede avisarle por WhatsApp |
| `proveedor@pedidos.test` | Proveedor | repuestos solicitados, cerrar pedido, Excel |
| `supervisor@pedidos.test` | Supervisor (rol creado) | ve pedidos de todos y la tabla del proveedor, sin modificar |
| `luis@pedidos.test` | Vendedor inactivo | no puede entrar |

Sin datos de ejemplo (solo admin, vendedor y proveedor): `php artisan migrate:fresh --seed`.

Pruebas (usan la base `pedidos_testing`, no tocan la de desarrollo):

```bash
docker compose exec app php artisan test
```

## Pedido de inventario

Lista compartida de repuestos que faltan en la tienda (código, descripción, cantidad). La ven todos
los vendedores y el proveedor. Si se agrega un código que ya está en la lista, el sistema avisa y
pregunta si se suma la cantidad (si el proveedor ya cerró ese pedido, va como línea nueva en el
siguiente). Tiene su **propio pedido al proveedor** («Inventario #N», numeración aparte), que se
cierra con «Cerrar inventario #N» en esta pantalla; los pedidos de clientes («Pedido #N») se cierran
aparte en «Repuestos solicitados». Cada uno se abre solo con el primer repuesto que se agregue. Lo que el proveedor marca enviado o descontinuado sale de la lista (pestaña «Enviados»).
Junto al título se ve el número del pedido abierto; la pestaña «Pedidos» lista cada pedido con su
**reporte** (vista previa, imprimir o PDF) con todos los movimientos: agregado, sumado, editado,
quitado, enviado, no disponible y descontinuado, con usuario y hora. Quitar no borra: queda en el reporte.

## Comprobante del pedido

En el pedido, «Imprimir / PDF» abre una vista previa (misma plantilla que el PDF:
`resources/views/comprobantes/pedido.blade.php`) con botones Imprimir y Descargar PDF.
El encabezado sale de `NEGOCIO_NOMBRE`, `NEGOCIO_TELEFONO`, `NEGOCIO_DIRECCION` y `NEGOCIO_NIT` en `.env`.

## WhatsApp

Cada usuario vincula **su propio** WhatsApp en «Mi WhatsApp» escaneando un QR (servicio `whatsapp`:
whatsapp-web.js, una sesión de Chromium por usuario, ~250–500 MB cada una; el contenedor tiene tope
de 3 GB). Los mensajes **se envían solos** desde el número de quien hace la acción:

| Cuándo | Sale del WhatsApp de | Le llega a |
|---|---|---|
| El proveedor marca enviado / no disponible / descontinuado | proveedor | vendedor del pedido (un mensaje por vendedor) |
| El proveedor cierra el pedido consolidado | proveedor | cada vendedor con líneas en él |
| Llega la fecha aprox. de ingreso (todos los días 08:00) | vendedor | proveedor(es) |
| «Preguntar al proveedor» en una línea sin existencia | vendedor | proveedor(es) |
| «WhatsApp al cliente» en el pedido (plantillas de usted, PDF opcional) | vendedor | cliente |

Todo aviso queda además en la campanita. Si quien envía no tiene su WhatsApp vinculado, el mensaje
queda «Falló» en «Mi WhatsApp» y se reintenta desde ahí. Los envíos de un mismo número se espacian
unos segundos. Cliente no oficial: WhatsApp puede limitar un número que lo use.

Después de cambiar jobs o comandos: `docker compose restart queue scheduler`.
