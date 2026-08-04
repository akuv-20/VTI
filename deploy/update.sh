#!/usr/bin/env bash
#
# Actualiza la plataforma VTI en producción tras un cambio en el repo.
#
#   Uso:   bash deploy/update.sh
#
# Trae el código, reinstala dependencias, recompila los assets, corre las
# migraciones, regenera las cachés de Laravel y recarga PHP-FPM (OPcache).
# El sitio queda en mantenimiento mientras dura la actualización y se levanta
# solo al terminar, incluso si algo falla.
#
# IMPORTANTE — archivos subidos:
# Actualiza sobre el mismo directorio (git pull), nunca lo reemplaza. Eso es lo
# que mantiene vivo `storage/app/public`, donde viven el logo, el favicon, el
# fondo del login y las fotos de los sitios. Si alguna vez cambias a un
# despliegue que clona o reemplaza la carpeta, hay que mover `storage/` a una
# ruta persistente y enlazarla, o esos archivos se pierden en cada paso.
#
# Variables opcionales (override):
#   PHP=php8.3  WEB_SERVICE=apache2  bash deploy/update.sh
#
# Por defecto usa el php del sistema (la app pide ^8.2) y detecta solo cómo
# refrescar OPcache: si hay PHP-FPM recarga ese servicio; si el PHP corre como
# módulo de Apache (mod_php), reinicia Apache, que es lo único que vacía su
# OPcache de verdad.

set -euo pipefail

# --- Config -------------------------------------------------------------
PHP="${PHP:-php}"
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "$APP_DIR"
echo "==> Proyecto: $APP_DIR"

# --- Modo mantenimiento (se levanta sí o sí al salir) -------------------
$PHP artisan down --retry=15 || true
trap '$PHP artisan up || true' EXIT

# --- Código -------------------------------------------------------------
echo "==> git pull"
git pull --ff-only

# --- Dependencias PHP ---------------------------------------------------
COMPOSER_BIN="$(command -v composer || echo /usr/local/bin/composer)"
echo "==> composer install"
$PHP "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

# --- Base de datos ------------------------------------------------------
echo "==> migraciones"
$PHP artisan migrate --force

# --- Frontend (Vite) ----------------------------------------------------
echo "==> build de assets (npm)"
npm ci
npm run build

# --- Archivos subidos ---------------------------------------------------
# El enlace public/storage está en .gitignore, así que no viaja en el repo:
# si falta, las imágenes responden 404 aunque el archivo esté en disco.
echo "==> verificando enlace de storage"
if [ ! -e public/storage ]; then
    echo "    public/storage no existe, creándolo"
    $PHP artisan storage:link
fi
for carpeta in config sitios; do
    n=$(find "storage/app/public/$carpeta" -type f 2>/dev/null | wc -l || echo 0)
    echo "    storage/app/public/$carpeta: $n archivo(s)"
done

# --- Cachés de Laravel --------------------------------------------------
# optimize:clear vacía route/config/view/cache; optimize vuelve a cachear
# config + rutas (aquí es donde se soluciona el 404 de rutas nuevas).
echo "==> regenerando cachés"
$PHP artisan optimize:clear
$PHP artisan optimize
$PHP artisan view:cache

# --- Permisos -----------------------------------------------------------
# Este script se corre como root, pero Apache atiende como www-data. Todo lo
# que Laravel escribe en caliente —logs, sesiones, vistas compiladas, fotos
# subidas— tiene que quedar del usuario web o la aplicación falla en runtime.
# Va después de las cachés, porque esos comandos también dejan archivos de root.
WEB_USER="${WEB_USER:-www-data}"
echo "==> permisos de storage y bootstrap/cache para $WEB_USER"
sudo chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache

# --- OPcache ------------------------------------------------------------
# Sin esto, con opcache.validate_timestamps=0 el PHP viejo sigue en memoria
# (rutas y controladores nuevos no se ven aunque el código ya esté en disco).
echo "==> refrescando OPcache"

if [ -n "${WEB_SERVICE:-}" ]; then
    echo "    servicio indicado a mano: $WEB_SERVICE"
    sudo systemctl restart "$WEB_SERVICE"
else
    FPM="$(systemctl list-units --type=service --all --no-legend 2>/dev/null \
           | grep -o 'php[0-9.]*-fpm\.service' | head -1 || true)"

    if [ -n "$FPM" ]; then
        echo "    PHP-FPM detectado: $FPM"
        sudo systemctl reload "$FPM"
    elif systemctl is-active --quiet apache2; then
        # mod_php: el OPcache lo crea el proceso padre de Apache, así que un
        # reload no lo vacía. Hay que reiniciar (el sitio ya está en mantenimiento).
        echo "    sin PHP-FPM; PHP corre dentro de Apache, se reinicia apache2"
        sudo systemctl restart apache2
    else
        echo "    !! no se detectó ni PHP-FPM ni Apache activo."
        echo "       Refresca OPcache a mano o pasa WEB_SERVICE=<servicio>."
    fi
fi

echo "==> Listo. Plataforma actualizada."
