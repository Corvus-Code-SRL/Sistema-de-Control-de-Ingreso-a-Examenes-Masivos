# SCIEM: despliegue automatizado con Docker Compose

Esta configuración produce una imagen con React compilado, Laravel/PHP 8.0.30 y Apache. Compose ejecuta PostgreSQL 15.0, Redis 7.4.3, `web`, `scheduler` y dos servicios temporales de instalación. No usa `php artisan serve` en producción ni cambia `composer.lock` o `package-lock.json`.

## Archivos y responsabilidad

| Archivo | Función |
| --- | --- |
| `.dockerignore` en la raíz del repositorio | Excluye `.env`, dependencias y datos locales del build. |
| `deployment/docker/apache/Dockerfile` | Compila frontend con Node 22.23.2/npm 10.9.8; instala backend con Composer 2.9.6 y PHP 8.0.30; incorpora Apache 2.4.62, phpredis 6.0.2 y cliente PostgreSQL 15.0. |
| `deployment/docker/apache/sciem.conf` | Sirve React en `/` y Laravel en `/api`, conservando `Authorization`. |
| `deployment/docker/apache/php-production.ini` | Configura errores, subida de archivos y OPcache. |
| `deployment/docker/apache/mpm-prefork.conf` | Limita Apache a 30 procesos en paralelo, por debajo de `max_connections` (100) de PostgreSQL. |
| `deployment/docker/postgres/init/01-sciem.sh` | Al crear un volumen PostgreSQL vacío, crea `pgcrypto`, el rol `sciem_app` y sus permisos iniciales. |
| `deployment/docker/scripts/entrypoint.sh` | Prepara directorios y `config:cache` en cada contenedor Laravel. |
| `deployment/docker/scripts/init-app.sh` | Aplica migraciones, ejecuta seeders normales y crea el primer administrador humano. |
| `deployment/docker/scripts/bootstrap-admin.php` | Alta inicial no interactiva desde variables; usa `UserService` y omite el alta si ya hay administrador humano activo. |
| `deployment/docker/scripts/finalize-db.sh` | Revoca `CREATE` sobre la base después de las migraciones. |
| `deployment/docker/docker-compose.yml` | Ordena infraestructura, inicialización y servicios permanentes. |
| `deployment/docker/.env.example` | Plantilla pública del **único** `.env` privado de despliegue. |
| `.gitattributes` en la raíz del repositorio | Fuerza fin de línea LF en los scripts, el Dockerfile y la configuración, aunque el repositorio se clone en Windows. |

La configuración de desarrollo `docker-compose.dev.yml` no cambia. El build de producción usa el `.dockerignore` de la **raíz**, porque su contexto es la raíz del repositorio. `backend/.env` queda para desarrollo y no se copia en la imagen.

## Primera instalación, en una base vacía

1. Instalar Docker Engine y Docker Compose con soporte de **contenedores Linux**: todas las imágenes (PHP, PostgreSQL, Redis) son Linux. En Linux funciona directo; en Windows Server hace falta WSL 2 o una máquina virtual Linux (Docker Desktop no está pensado para Windows Server). Confirmarlo con infraestructura antes del deploy. Preparar dominio, TLS y proxy inverso. El ejemplo publica Apache solo en `127.0.0.1:18080`, por lo que supone un proxy en el mismo host; sin proxy, ver «Red, proxy y límites».
   Si el repositorio se clonó en Windows, comprobar que los scripts tienen fin de línea LF: `git ls-files --eol deployment/docker/scripts deployment/docker/postgres/init` debe mostrar `w/lf` en todos. Con `w/crlf`, los scripts no arrancan dentro del contenedor; volver a clonar el repositorio después de integrar `.gitattributes`.
2. Desde la raíz del repositorio, crear `deployment/docker/.env` copiando `.env.example`, completar todas las variables y protegerlo con `chmod 600 deployment/docker/.env`. **No subir `.env` a Git.**
3. Usar contraseñas diferentes para `POSTGRES_PASSWORD`, `DB_PASSWORD` y `BOOTSTRAP_ADMIN_PASSWORD`. Reemplazar todos los marcadores de ejemplo. `BOOTSTRAP_ADMIN_CODE` debe ser el código real de la persona autorizada, no una cuenta de prueba.
4. Generar `APP_KEY` una sola vez y guardarla fuera del servidor junto con los respaldos. Si hay PHP local: `php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'`.
5. Para simulación local: `APP_URL=http://localhost:18080` y `VITE_API_URL=http://localhost:18080/api`. En el servidor usar `https://dominio-real` y `https://dominio-real/api` **antes de construir**; Vite incorpora la URL al JavaScript.
6. Validar sin imprimir secretos y ejecutar el despliegue:

```bash
docker compose --env-file deployment/docker/.env \
  -f deployment/docker/docker-compose.yml -p sciem-prod config --quiet

docker compose --env-file deployment/docker/.env \
  -f deployment/docker/docker-compose.yml -p sciem-prod up --build -d
```

`postgres` y `redis` deben pasar sus healthchecks. PostgreSQL ejecuta `01-sciem.sh` solo cuando `pg_data` está vacío. `app-init` espera a ambos, aplica las migraciones y seeders normales, y crea el administrador inicial si aún no existe. `db-finalize` espera a `app-init`, revoca el permiso temporal y termina. Solo después arrancan `web` y `scheduler`. Si falla un paso, `web` no debe publicarse.

Verificar:

```bash
docker compose --env-file deployment/docker/.env \
  -f deployment/docker/docker-compose.yml -p sciem-prod ps -a

docker compose --env-file deployment/docker/.env \
  -f deployment/docker/docker-compose.yml -p sciem-prod logs --tail=80 app-init db-finalize web scheduler

curl -I http://localhost:18080/
```

`app-init` y `db-finalize` deben figurar como `Exited (0)`, `web` y `scheduler` como activos. El login con un cuerpo vacío debe devolver JSON de validación (HTTP 422), no HTML. Iniciar sesión con el administrador inicial, cambiar su contraseña temporal, verificar permisos, una ruta interna de React, API, archivos y scheduler.

**Después del primer inicio de sesión:** retirar `BOOTSTRAP_ADMIN_PASSWORD` del `.env` privado. El bootstrap detecta que ya existe un administrador humano y no exige esa variable en arranques posteriores. Recrear `app-init` si cambia el `.env`; no usar solo `restart`. El contenedor temporal terminado conserva su antiguo entorno hasta que se elimine o recree, por lo que debe retirarse después de la rotación. No se imprime la contraseña en logs.

## Periodo académico vigente (obligatorio tras la primera instalación)

Una base nueva no tiene ninguna gestión ni periodo. Sin un periodo **vigente**, el catálogo académico, los grupos y los exámenes responden HTTP 500 con «No hay un período académico vigente…». `app-init` lo avisa en su log (`SCIEM_PERIODO_ACTIVO_ID no está definida`). Todavía no hay pantalla para registrarlo.

Después de que `app-init` y `db-finalize` terminen en `Exited (0)`, crear el periodo vigente. El ejemplo crea la gestión 2026, el periodo 2 y lo marca como vigente; se puede repetir sin duplicar datos:

```bash
docker compose --env-file deployment/docker/.env \
  -f deployment/docker/docker-compose.yml -p sciem-prod \
  exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -v ON_ERROR_STOP=1' <<'SQL'
BEGIN;
INSERT INTO gestion (anio) VALUES (2026) ON CONFLICT (anio) DO NOTHING;
INSERT INTO gestion_periodo (id_gestion, id_periodo, vigente, estado)
SELECT g.id_gestion, p.id_periodo, false, 'ACTIVO'
  FROM gestion g JOIN periodo p ON p.codigo = 2
 WHERE g.anio = 2026
   AND NOT EXISTS (SELECT 1 FROM gestion_periodo gp
                    WHERE gp.id_gestion = g.id_gestion AND gp.id_periodo = p.id_periodo);
UPDATE gestion_periodo SET vigente = false WHERE vigente;
UPDATE gestion_periodo gp SET vigente = true
  FROM gestion g, periodo p
 WHERE gp.id_gestion = g.id_gestion AND gp.id_periodo = p.id_periodo
   AND g.anio = 2026 AND p.codigo = 2;
COMMIT;
SELECT p.codigo || '/' || g.anio AS periodo_vigente
  FROM gestion_periodo gp JOIN gestion g USING (id_gestion) JOIN periodo p USING (id_periodo)
 WHERE gp.vigente;
SQL
```

Debe mostrar `periodo_vigente = 2/2026`. Para otro periodo, cambiar `2026` y `codigo = 2` (por ejemplo `2027` y `codigo = 1` para el 1/2027). Al cambiar de periodo se ejecuta lo mismo con los valores nuevos: queda vigente solo el último.

## Actualizaciones y datos existentes

`up --build -d` puede repetirse: `migrate` aplica solo migraciones pendientes, los seeders normales son reutilizables, el bootstrap no duplica administradores y `REVOKE` admite repetición. En una actualización real, respaldar primero la base y las evidencias, revisar las nuevas migraciones y cerrar o poner en mantenimiento el tráfico antes de aplicar cambios de esquema. Una migración futura que cree esquemas o extensiones puede requerir permisos adicionales; comprobarlo antes de actualizar.

Los volúmenes `pg_data`, `redis_data`, `app_storage` y `backups` conservan datos. **No ejecutar `docker compose down -v`** con datos importantes. El script de `/docker-entrypoint-initdb.d` no se vuelve a ejecutar sobre un volumen existente: una base anterior necesita un procedimiento de migración y permisos propio, no esta instalación inicial. `TestDataSeeder` y `docs/database/creation-script.sql` no se cargan en esta instalación; la base empieza vacía y Laravel la crea con migraciones.

Los respaldos dentro del volumen `backups` no protegen contra la pérdida del servidor. Copiar externamente la base, las evidencias y `APP_KEY`; ensayar una restauración en otra instancia.

## Red, proxy y límites

Compose crea una red interna: `DB_HOST=postgres` y `REDIS_HOST=redis`. Redis no publica puertos. PostgreSQL publica `5433` solo en `127.0.0.1` para administración local o mediante túnel SSH; no queda accesible directamente desde otras PC. Apache expone `127.0.0.1:18080`; el proxy institucional debe ofrecer HTTPS, conservar `Host` y `Authorization`, reenviar `X-Forwarded-Proto`, `X-Forwarded-For` y `X-Forwarded-Port`, y aceptar cargas de al menos 16 MB. Si el proxy está en otro servidor o contenedor, adaptar red y firewall antes de desplegar.

Detrás del proxy, Laravel tiene que saber que puede confiar en él para tomar la IP real del cliente (límites por IP del RNF-03, bitácora) y el esquema HTTPS. Se declara con `SCIEM_PROXIES_CONFIABLES` en el `.env`:

| Situación | `WEB_BIND_IP` / `WEB_PORT` | `SCIEM_PROXIES_CONFIABLES` |
| --- | --- | --- |
| Proxy en el mismo servidor (ejemplo por defecto) | `127.0.0.1` / `18080` | `*` (nadie más llega a Apache) |
| Proxy en otro servidor | IP de la interfaz que ve el proxy / `18080` | IP del proxy, p. ej. `10.0.0.5` (varias separadas por coma) |
| Sin proxy, acceso directo por HTTP | `0.0.0.0` / `80` | vacío |

Nunca usar `*` si Apache es accesible directamente desde la red: un cliente podría falsificar su IP con `X-Forwarded-For`. Antes de publicar, verificar desde clientes distintos la IP que registra la bitácora y que los límites por IP funcionen.

## Rendimiento

- Apache atiende hasta 30 peticiones en paralelo (`apache/mpm-prefork.conf`). Un ingreso masivo de ~1.500 postulantes son unas 10 peticiones por segundo de pocos milisegundos. Subirlo solo si se ven peticiones en espera, siempre por debajo de `max_connections` (100).
- PostgreSQL anota toda consulta de más de 500 ms y toda espera larga de lock (`command` del servicio `postgres`): revisar con `logs postgres` después de un examen. Es evidencia del RNF de rendimiento.
- `web` tiene healthcheck: Apache sirve la SPA y Laravel responde en `/api`. `ps` lo muestra como `healthy`.

Mantener las versiones fijadas en el Dockerfile y los lockfiles; no ejecutar `composer update` ni `npm update` durante el despliegue. `apt-get` se ejecuta **dentro** de la imagen PHP Debian Bullseye, no en el sistema operativo del servidor.
