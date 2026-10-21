# Despliegue en producción

Vive en el VPS de Taller-sis (`187.77.16.242`), en `/root/sistema-pedidos`, con el dominio
**fadeaap.com** (DNS: A `@` → 187.77.16.242, CNAME `www` → fadeaap.com).

El nginx de Taller-sis (`taller_nginx_prod`) tiene los puertos 80/443 y el certbot. Al final de
`/root/Taller-sis/docker/nginx/production.conf` están los bloques de fadeaap.com, que reenvían a
`pedidos-web` por la red `taller-sis_default`. Ese archivo es un bind mount de archivo suelto:
editarlo con `cat >>` o un editor, nunca `sed -i`, y luego `docker exec taller_nginx_prod nginx -t`
y `nginx -s reload`. Un `git pull` de Taller-sis que cambie ese archivo chocará con estos bloques.

## Primera vez

```bash
git clone https://github.com/Hugoda23/sistema-pedidos.git /root/sistema-pedidos
cd /root/sistema-pedidos
cp .env.production.example .env.production   # llenar DB_PASSWORD, WHATSAPP_TOKEN, APP_KEY
chown -R 1000:1000 backend                   # php corre como UID 1000

alias dc='docker compose -f docker-compose.prod.yml --env-file .env.production'
dc build
dc run --rm app composer install --no-dev --optimize-autoloader
dc run --rm app php artisan key:generate --show   # pegar en APP_KEY
dc up -d
dc exec app php artisan migrate --force
dc exec app php artisan db:seed --class=RolesYPermisosSeeder --force
# NO correr el DatabaseSeeder: crea usuarios @pedidos.test con clave «password» (el repo es público).
# Se crea solo un administrador con clave aleatoria; el resto de usuarios los da de alta él.
dc exec app php artisan tinker --execute='$p=Str::password(14,symbols:false); App\Models\User::create(["name"=>"Administrador","email"=>"admin@fadeaap.com","password"=>$p])->syncRoles(["Administrador"]); echo $p;'
dc exec app php artisan optimize
```

## Actualizar

```bash
cd /root/sistema-pedidos && git pull
dc run --rm app composer install --no-dev --optimize-autoloader
dc up -d --build web
dc exec app php artisan migrate --force
dc exec app php artisan optimize
dc restart queue scheduler
```

## Respaldo de la base

Diario a las 03:00 de Guatemala (09:00 UTC, el VPS está en UTC) con `scripts/respaldo-diario.sh`:
guarda `/root/backups/pedidos_diario_*.sql.gz` y conserva 14 días. Bitácora en `/var/log/respaldo-pedidos.log`.

```bash
# /etc/cron.d/respaldo-pedidos
0 9 * * * root /root/sistema-pedidos/scripts/respaldo-diario.sh >> /var/log/respaldo-pedidos.log 2>&1
```

A mano (p. ej. antes de desplegar):

```bash
docker exec pedidos_db pg_dump -U pedidos pedidos | gzip > /root/backups/pedidos_$(date +%Y%m%d_%H%M%S).sql.gz
```

Restaurar (detiene la app para que nadie escriba mientras tanto):

```bash
dc stop app queue scheduler
zcat /root/backups/pedidos_diario_AAAAMMDD_HHMMSS.sql.gz | docker exec -i pedidos_db psql -U pedidos -d pedidos -q
dc start app queue scheduler
```
