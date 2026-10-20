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

Pruebas (usan la base `pedidos_testing`, no tocan la de desarrollo):

```bash
docker compose exec app php artisan test
```
