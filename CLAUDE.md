# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Plataforma interna de TI de Unifrutti/Verfrut (Laravel 12, PHP ^8.2, MySQL, Blade).
El código, los comentarios, los nombres de tablas y columnas y la interfaz están
**en español**: mantener esa convención al escribir código nuevo.

## Comandos

```bash
php artisan migrate            # aplicar migraciones
php artisan view:clear         # tras editar Blade, si algo no se refleja
php artisan optimize:clear     # limpiar config/rutas/vistas cacheadas
npm run dev                    # Vite en desarrollo
npm run build                  # compilar assets
```

Pruebas (PHPUnit vía `php artisan test`):

```bash
php artisan test --filter=NombreDelTest
```

Ojo: `tests/` solo contiene los ejemplos que trae Laravel. **No hay suite real**,
así que `artisan test` no protege de regresiones. Para verificar un cambio, lo
que funciona aquí es un script PHP suelto que arranque la aplicación y ejercite
el código o los controladores directamente, ejecutado con `php <archivo>`.

### Entorno de desarrollo (Windows + Herd)

- `php` está en el PATH de **PowerShell**, no en el de la herramienta Bash.
- `php artisan tinker --execute` es poco fiable: PowerShell destroza las `$vars`
  y las comillas. Preferir un script en un archivo.
- Mensajes de commit multilínea: pasarlos con `git commit -F archivo.txt`. Los
  here-strings con comillas dobles o rutas se rompen o disparan guardias.

## Despliegue

```bash
cd /var/www/VTI && bash deploy/update.sh
```

Ver [deploy/update.sh](deploy/update.sh). Actualiza con `git pull` sobre el mismo
directorio — nunca lo reemplaza —, que es lo que mantiene vivos los archivos de
`storage/app/public`. Detecta solo cómo refrescar OPcache (FPM si existe; si no,
reinicia Apache) y devuelve `storage` y `bootstrap/cache` al usuario web.

## Arquitectura

### Configuración viva en la base de datos

Muchas credenciales **no** salen del `.env` sino de la tabla `configuraciones`
(modelo `Configuracion`, pantalla Admin → Configuración). El `.env` es solo el
respaldo. La sobreescritura ocurre en `AppServiceProvider::boot()`, no en los
archivos de `config/`: ahí la base de datos todavía no existe y cualquier
consulta falla en silencio. Aplica a LDAP, LDAP secundario y la conexión GLPI.

Al agregar una integración configurable, seguir ese patrón: valores por defecto
en `config/`, sobreescritura en `boot()`.

### Dos conexiones de base de datos

- `mysql` — la aplicación.
- `glpi` — la base de GLPI, **solo lectura**, consultada con el Query Builder
  (no hay modelos Eloquent para sus tablas).

### Permisos

Dos capas que conviven:

1. **Módulos** (`modulos` + pivote `modulo_user`): cada módulo declara
   `route_prefixes`, y `User::tieneAcceso('nombre.de.ruta')` compara por
   prefijo. `es_admin` pasa por encima de todo.
2. **Gates** definidos en `AppServiceProvider::boot()` (`acceso_kpi`,
   `acceso_monitoreo`, `acceso_sitios`, `acceso_ad`, `acceso_entra`, `admin`),
   que casi siempre delegan en `tieneAcceso()`.

Las rutas nuevas se protegen con `middleware(['auth', 'can:...'])`, y el menú de
`layouts/app.blade.php` usa el helper `$ta('ruta.index')` para mostrar u ocultar
cada entrada. Un módulo nuevo necesita: migración que lo inserte en `modulos`,
Gate, rutas con middleware y entrada en el menú.

### Integraciones externas

| Servicio | Dónde | Nota |
|---|---|---|
| CheckMK 2.3 (REST) | `App\Services\CheckMkClient` | La disponibilidad histórica no está en la API; se obtiene exportando la vista `view.py`. |
| CheckMK ↔ fichas | `SitiosCheckMk`, `ReferenciasCheckMk` | Descubrimiento de hosts sin ficha y detección de enlaces rotos. |
| GLPI | conexión `glpi` | Inventario de equipos. |
| AD / Entra ID | LdapRecord, Microsoft Graph | `config/ldap.php` solo declara `default`; la segunda conexión (`secondary`, Perú) se registra en caliente desde `AppServiceProvider::boot()` si hay credenciales guardadas. |

Todo lo que dependa de un sistema externo va **cacheado y dentro de `try/catch`**,
y el fallo se cachea igual que el éxito: un `null` no se distingue de una caché
vacía y haría reintentar la consulta en cada carga. Ver `HomeController`.

### Módulos funcionales

Agrupados como en el menú lateral: Facturación y Telefonía (el núcleo original,
con importaciones de Entel/Movistar/WOM), Inventario TI, AD/EntraID, Monitoreo
(mapa de red y fichas de sitios) y KPIs. El detalle del módulo KPI —metas,
escalas y qué está construido— está en [contexto_kpi_proyecto.md](contexto_kpi_proyecto.md).

### Frontend

Blade + Bootstrap 5. Los estilos propios van en un `<style>` al inicio de cada
vista con prefijo corto por pantalla; no hay sistema de componentes. Vite compila
`resources/sass/app.scss` y `resources/js/app.js`; Bootstrap Icons, Chart.js,
select2 y tom-select se cargan por CDN donde se usan.

**Trampa de Blade que ya mordió varias veces:** `@json()` no admite funciones
flecha ni closures dentro (`@json(collect(...)->map(fn(...)))` explota al
compilar). Armar el arreglo en un bloque `@php` y pasar solo datos planos.
Tampoco se reconoce `@if` pegado a una letra (`texto@if(...)`), pero sí se
compila el `@endif`, lo que produce un error de sintaxis desconcertante.

### Tarea programada

`kpi:capturar-disponibilidad` corre el día 1 de cada mes a las 03:00
(America/Santiago) y congela el snapshot mensual del KPI de disponibilidad.
Requiere que el scheduler de Laravel esté activo en el servidor.
