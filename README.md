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
| `vendedor@pedidos.test` (Carlos Méndez) | Vendedor | sus pedidos, clientes, productos |
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

## Comprobante del pedido

En el pedido, «Imprimir / PDF» abre una vista previa (misma plantilla que el PDF:
`resources/views/comprobantes/pedido.blade.php`) con botones Imprimir y Descargar PDF.
El encabezado sale de `NEGOCIO_NOMBRE`, `NEGOCIO_TELEFONO`, `NEGOCIO_DIRECCION` y `NEGOCIO_NIT` en `.env`.

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
