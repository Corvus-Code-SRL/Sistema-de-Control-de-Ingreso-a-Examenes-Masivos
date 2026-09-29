# Migración base del esquema

`backend/database/migrations/2026_09_25_000000_create_baseline_schema.php` reproduce el esquema vigente
(7 tipos enum, 36 tablas, índices, restricciones y un trigger, con HU-21, HU-24, las tablas de auxiliares
(`grupo_auxiliar`, `examen_auxiliar`), `examen.minutos_apertura` y `periodo.gestion` ya incorporados).
Ejecuta una copia de `docs/database/creation-script.sql` (que debe ser idéntica: lo verifica `DatabaseSchemaTest`) guardada en
`backend/database/schema/baseline.sql`. Ese archivo y la migración no se editan nunca: todo cambio
estructural posterior va en una migración nueva (`php artisan make:migration ...`).

Sustituye a las seis migraciones parciales anteriores (`create_enum_types`, `create_rol_table`,
`create_usuario_table`, `create_usuario_rol_table`, `create_accion_table`, `create_log_table`), que solo
cubrían 6 de las tablas y ya no coincidían con el script. Se conservan las de Laravel/Sanctum
(`failed_jobs`, `personal_access_tokens`), que no forman parte del script.

`down()` lanza una excepción a propósito: revertir la base borraría todo el esquema.

## 1. Base nueva (PostgreSQL 15 limpio, p. ej. el servidor del Sprint 6)

```bash
createdb -U postgres sciem_db
cd backend
php artisan migrate
```

Requiere que el usuario pueda ejecutar `CREATE EXTENSION pgcrypto` (el script lo incluye). Verificado en
PostgreSQL 15.19 limpio: el resultado es idéntico al de cargar `creation-script.sql`, más las tablas
`migrations`, `failed_jobs` y `personal_access_tokens` de Laravel.

## 2. Base compartida donde el esquema ya existe (Supabase)

**Ya está hecho (2026-09-26).** La base compartida se llevó al esquema final con
`docs/database/alter-hu-24-examen.sql` y `docs/database/alter-esquema-final.sql` (ambos idempotentes, en una
sola transacción), se creó la tabla `migrations` y se marcó la migración base como aplicada
(`migrate:status` la muestra en `Yes`, lote 1). Nadie más debe repetirlo: en Supabase basta con
`php artisan migrate` cuando haya migraciones nuevas.

Quedan pendientes las dos migraciones de Laravel (`failed_jobs` y `personal_access_tokens`, esta última la
usa Sanctum). Son tablas nuevas que no tocan datos; se crean con `php artisan migrate` cuando el equipo lo
decida. Hasta entonces `migrate --pretend` solo debe listar esas dos.

Si alguna vez hay que repetir el procedimiento en otra base con el esquema ya creado (la migración base **no
debe ejecutarse** ahí: fallaría en el primer `CREATE TYPE`; se marca como aplicada):

1. Comparar el esquema vivo con `creation-script.sql`. Si difiere, llevarlo al esquema final aplicando
   `alter-hu-21-estudiante-ci.sql`, `alter-hu-24-examen.sql` y `alter-esquema-final.sql` (en ese orden).
   Comprobar que la base no tenga filas o que los `alter` no las dañen.
2. Crear la tabla de control (solo agrega una tabla):

   ```bash
   php artisan migrate:install
   ```

3. Comprobar con `--pretend`, que no ejecuta nada, qué haría `migrate`:

   ```bash
   php artisan migrate --pretend
   ```

4. Marcar la migración base como ejecutada:

   ```bash
   php artisan tinker --execute="DB::table('migrations')->insert(['migration' => '2026_09_25_000000_create_baseline_schema', 'batch' => DB::table('migrations')->max('batch') + 1]);"
   ```

   Equivale al SQL: `INSERT INTO migrations (migration, batch) VALUES ('2026_09_25_000000_create_baseline_schema', 1);`

5. Repetir `php artisan migrate --pretend`: la migración base ya no debe aparecer. Comprobar con
   `php artisan migrate:status`.

Diferencia del esquema de Supabase frente al de una base local: `pgcrypto` vive en el esquema `extensions`
(no en `public`), y Supabase trae sus propios esquemas (`auth`, `storage`, `realtime`...). No afectan a `public`.

## 3. Bases locales creadas con las migraciones parciales antiguas

Su tabla `migrations` conserva seis filas de archivos que ya no existen (es inofensivo), pero la migración
base figura como pendiente y fallaría. Opciones: recrear la base local (`DROP DATABASE` / `createdb` /
`php artisan migrate`) o marcar la migración base como en el punto 2, pasos 3 a 5.

## 4. Pruebas automáticas

`tests/TestCase.php` sigue cargando `docs/database/creation-script.sql` en `sciem_test`. Mientras ese
script no se actualice a la par de cada migración nueva, las pruebas no verán los cambios estructurales
posteriores a la base. Pendiente de decisión del equipo: que `TestCase` cargue el esquema con
`php artisan migrate` en lugar del script, conservando intacta la verificación del nombre `sciem_test`.

Si una base local ya ejecutó la migración base **antes del 2026-09-26**, tiene una versión anterior del esquema
(sin las tablas de auxiliares, sin `minutos_apertura` ni el trigger de cancelación) y `migrate` no la volverá a
correr. Hay que recrear esa base local (`DROP DATABASE` / `createdb` / `php artisan migrate`). A partir de
ahora la migración base no cambia más: todo cambio estructural va en una migración nueva.
