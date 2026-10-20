# Sistema de Pedidos

Laravel 13 (API) · React + Vite + Tailwind · PostgreSQL 16 · Docker.

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

- Frontend: http://localhost:5180
- API: http://localhost:8120/api
- Postgres: localhost:5445 (pedidos / secret)

Usuarios de prueba (contraseña `password`): `admin@pedidos.test`, `vendedor@pedidos.test`, `proveedor@pedidos.test`.
Pedidos de ejemplo: `docker compose exec app php artisan db:seed --class=DemoSeeder` (agrega `vendedor2@pedidos.test`).

Pruebas (usan la base `pedidos_testing`, no tocan la de desarrollo):

```bash
docker compose exec app php artisan test
```

## WhatsApp

Cada usuario vincula **su propio** WhatsApp en «Mi WhatsApp» escaneando un QR (servicio `whatsapp`,
whatsapp-web.js, una sesión de Chromium por usuario, ~200–500 MB cada una). Los avisos salen del
número de quien hace la acción hacia el teléfono guardado en el usuario que los recibe:

| Cuándo | Desde | Hacia |
|---|---|---|
| El proveedor marca enviado / no disponible / descontinuado | proveedor | vendedor del pedido |
| El proveedor cierra el pedido consolidado | proveedor | cada vendedor con líneas en él |
| Llega la fecha aprox. de ingreso (todos los días 08:00) | vendedor | proveedor(es) |
| «WhatsApp al cliente» en el pedido | vendedor | cliente |

Además, todo aviso aparece en la campanita. Si el remitente no tiene su WhatsApp vinculado, el mensaje
queda como «Falló» en «Mi WhatsApp» y se puede reintentar. Cliente no oficial: WhatsApp puede limitar
un número que lo use; los envíos se espacian unos segundos por número.

Después de cambiar jobs o comandos: `docker compose restart queue scheduler`.
