#!/usr/bin/env bash
# Respaldo diario de la base de producción. Lo corre cron en el VPS (ver DESPLIEGUE.md).
# Guarda /root/backups/pedidos_diario_AAAAMMDD_HHMMSS.sql.gz y borra los de más de DIAS días.
set -euo pipefail

DESTINO=${DESTINO:-/root/backups}
DIAS=${DIAS:-14}
archivo="$DESTINO/pedidos_diario_$(date +%Y%m%d_%H%M%S).sql.gz"
tmp="$archivo.parcial"

mkdir -p "$DESTINO"
trap 'rm -f "$tmp"' EXIT

docker exec pedidos_db pg_dump -U pedidos --clean --if-exists pedidos | gzip > "$tmp"
gzip -t "$tmp"
# Un volcado sano trae la tabla de usuarios; si no, mejor fallar que guardar basura.
zcat "$tmp" | grep -q 'CREATE TABLE public.users' || { echo "respaldo incompleto: $tmp" >&2; exit 1; }
mv "$tmp" "$archivo"

find "$DESTINO" -name 'pedidos_diario_*.sql.gz' -mtime +"$DIAS" -delete
echo "$(date '+%F %T') ok $(du -h "$archivo" | cut -f1) $archivo"
