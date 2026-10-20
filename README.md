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
Pedidos de ejemplo: `docker compose exec app php artisan db:seed --class=DemoSeeder` (agrega `vendedor2@pedidos.test`, teléfonos y pedidos en distintos estados).

Pruebas (usan la base `pedidos_testing`, no tocan la de desarrollo):

```bash
docker compose exec app php artisan test
```

## WhatsApp

Sin sesiones ni QR: los avisos son botones verdes que abren el WhatsApp de quien hace clic
(celular o WhatsApp Web) con el mensaje ya escrito, hacia el teléfono guardado del destinatario;
solo se presiona enviar. Todo aviso queda además en la campanita.

| Cuándo | Botón que aparece | Le llega a |
|---|---|---|
| El proveedor marca enviado / no disponible / descontinuado | en la tabla del proveedor, uno por vendedor | vendedor |
| El proveedor cierra el pedido consolidado | en la tabla del proveedor, uno por vendedor | vendedor |
| Llega la fecha aprox. de ingreso (todos los días 08:00) | en la campanita del vendedor | proveedor |
| Línea sin existencia | «Preguntar al proveedor» en el pedido | proveedor |
| «WhatsApp al cliente» en el pedido (plantillas) | en el pedido | cliente |

Los teléfonos de 8 dígitos se envían con el prefijo `WHATSAPP_PAIS` (502).
Después de cambiar jobs o comandos: `docker compose restart queue scheduler`.
