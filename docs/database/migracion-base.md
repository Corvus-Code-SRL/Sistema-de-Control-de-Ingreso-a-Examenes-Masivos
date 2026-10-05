# Migración base del esquema

`backend/database/migrations/2026_09_25_000000_create_baseline_schema.php` reproduce el esquema vigente
(7 tipos enum, 36 tablas, índices, restricciones y un trigger, con HU-21, HU-24, las tablas de auxiliares
(`grupo_auxiliar`, `examen_auxiliar`), `examen.minutos_apertura` y `periodo.gestion` ya incorporados).
Ejecuta `backend/database/schema/baseline.sql`, que es el comienzo exacto de `docs/database/creation-script.sql`
(lo verifica `DatabaseSchemaTest`). Ese archivo y la migración no se editan nunca: todo cambio
estructural posterior va en una migración nueva (`php artisan make:migration ...`) y, para que
`creation-script.sql` siga siendo el esquema final, el SQL equivalente se **agrega al final** de ese script, en la
sección «CAMBIOS POSTERIORES A LA BASE» (hoy: la migración `2026_09_29_000001` de HU-11).

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
PostgreSQL 15.19 limpio: el resultado es idéntico al de cargar `creation-script.sql` completo (baseline más los cambios posteriores), más las tablas
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

`tests/TestCase.php` arma `sciem_test` con `php artisan migrate` (baseline y migraciones posteriores, incluidas las de
Sanctum), después de comprobar que la base se llama `sciem_test`: si no, aborta antes de borrar nada. Así las
pruebas siempre ven el esquema que producen las migraciones, y no el del script.

`creation-script.sql` sigue siendo la referencia legible y lo que carga el servicio `postgres` de Docker en una base
nueva. Para que no se desfase, `DatabaseSchemaTest` comprueba dos cosas: que el script empieza con el baseline sin
cambios y que contiene los objetos que agregan las migraciones posteriores. **Al crear una migración estructural:**

1. Escribe la migración (`up` y `down`).
2. Agrega al final de `creation-script.sql` el SQL equivalente, con los mismos nombres de restricciones e índices
   que genera la migración (compara con `pg_dump -s` de una base migrada y otra cargada desde el script; la de la
   HU-11 coincide en sus 284 objetos).
3. Ajusta `testCreationScriptDeclaresWhatLaterMigrationsAdd` si la migración agrega objetos nuevos.

Las migraciones **de datos** no cambian el esquema y no se reflejan en `creation-script.sql`, que no contiene datos: por ejemplo `2026_10_04_000000_delete_legacy_accion_rows`, que borra cuatro filas heredadas del catálogo `accion` en la base compartida y no hace nada en una base nueva.

Una base creada con el script de Docker ya trae esos cambios: no se le corre `migrate` (la migración base fallaría);
sirve para desarrollar, no para probar migraciones.

Si una base local ya ejecutó la migración base **antes del 2026-09-26**, tiene una versión anterior del esquema
(sin las tablas de auxiliares, sin `minutos_apertura` ni el trigger de cancelación) y `migrate` no la volverá a
correr. Hay que recrear esa base local (`DROP DATABASE` / `createdb` / `php artisan migrate`). A partir de
ahora la migración base no cambia más: todo cambio estructural va en una migración nueva.
