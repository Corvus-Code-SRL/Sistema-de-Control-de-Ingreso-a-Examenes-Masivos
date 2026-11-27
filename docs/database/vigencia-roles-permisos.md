# Vigencia de roles y permisos (RNF-01)

La base guarda **desde y hasta cuándo** valió cada asignación, para poder responder después
«¿qué hizo esta cuenta mientras fue Administrador?» aunque hoy tenga otro rol.

## Tablas

| Tabla | Qué guarda | Quién la llena |
|---|---|---|
| `usuario_rol` | Períodos de rol de cada cuenta (`fecha_inicio`, `fecha_fin`). Ya existía. | HU «Asignar rol a usuario» |
| `permiso` | Un permiso por función de `config('sciem.access')` y uno propio por rol (`own_auxiliar`, `own_administrador`). | `RolePermissionSeeder` |
| `permiso_rol` | Desde y hasta cuándo un rol tuvo un permiso. Nuevo en RNF-01: `fecha_inicio`, `fecha_fin`. | `RolePermissionSeeder` |
| `ui` | Una fila por pantalla (`bitacora.consultar`, `ingreso.operar`, …). | `RolePermissionSeeder` |
| `ui_permiso` | Qué pantallas habilita cada permiso (`estado` ACTIVO/INACTIVO). | `RolePermissionSeeder` |
| `log` | Cada operación con `id_usuario` y `fecha_hora`. El rol **no** se guarda: se calcula. | `AuditLogService` |

## Reglas

- Una fila de `permiso_rol` nunca se borra. Quitar un permiso a un rol pone `fecha_fin`; devolverlo abre
  una fila nueva. El índice parcial `unq_permiso_rol_vigente` impide dos filas abiertas del mismo par.
- Los permisos son fijos por rol: se cambian en `config/sciem.php` (y en `capabilities.ts`; la prueba
  `capabilities.access.test.ts` falla si difieren) y el seeder los aplica en el siguiente `up`.
- El seeder es idempotente: correrlo dos veces seguidas no crea filas ni registros de bitácora.
- Cada alta o cierre que hace el seeder queda en `log` con el actor del sistema.
- El rol de una operación es el período de `usuario_rol` con `fecha_inicio <= log.fecha_hora` y
  `fecha_fin` nula o posterior. El instante exacto del cambio ya pertenece al rol nuevo.

## Mantener sincronizadas las capacidades

- El mapa `capacidades` de `config/sciem.php` debe coincidir con `CAPABILITIES_BY_FEATURE` en
  `frontend/src/features/auth/capabilities.ts`. Si difieren, falla `capabilities.access.test.ts`.
- Un PR que agregue o cambie capacidades debe tocar ambos archivos en el mismo cambio.
- `RolePermissionSeeder` debe ejecutarse después de todo PR que agregue capacidades: hasta entonces
  `permiso`, `permiso_rol`, `ui` y `ui_permiso` no tienen las filas nuevas y la base no refleja el código.

## Reversión y respaldo

La migración `2026_11_26_000001_add_vigencia_to_permiso_rol` es reversible en estructura, pero **no en
datos**. Su `down()` conserva una sola fila por par permiso–rol (la más reciente) y elimina las
columnas `fecha_inicio` y `fecha_fin`: **revertir descarta el historial de vigencia**.

Por eso se respalda `permiso_rol` **antes de aplicar y antes de revertir** la migración. Los valores entre
`<>` son placeholders; la contraseña no va en el comando (usar `PGPASSWORD` o `~/.pgpass`):

```bash
pg_dump -h <HOST> -p <PUERTO> -U <USUARIO> -d <BASE> \
  --table=public.permiso_rol --data-only -Fc -f permiso_rol_<FECHA>.dump
```

Un volcado de solo datos depende de las columnas que tenía la tabla al generarlo, así que cómo se
restaura depende de cuándo se tomó. Ambos procedimientos son **operaciones coordinadas**: las ejecuta
una sola persona, avisada antes en el canal del equipo, y nunca se corren contra Supabase sin esa
coordinación. Más sobre respaldos en `respaldo-y-restauracion.md`.

### a) Respaldo tomado antes de aplicar la migración

El volcado tiene la estructura antigua (sin columnas de vigencia). Se restaura sobre esa misma
estructura:

1. Dejar la base con la estructura antigua (migración no aplicada, o revertida con `down()`).
2. Vaciar la tabla: `TRUNCATE public.permiso_rol;`
3. Restaurar:

```bash
pg_restore -h <HOST> -p <PUERTO> -U <USUARIO> -d <BASE> \
  --data-only --table=permiso_rol permiso_rol_<FECHA>.dump
```

### b) Respaldo tomado antes de revertir

El volcado tiene `fecha_inicio` y `fecha_fin`, que `down()` elimina; restaurarlo después de revertir
falla. Para recuperar el historial:

1. Volver a aplicar la migración (`up`), para que existan de nuevo las columnas de vigencia.
2. Vaciar la tabla: `TRUNCATE public.permiso_rol;` (descarta las filas que `up` pobló con la fecha actual).
3. Restaurar el volcado con el mismo `pg_restore` del caso a).

## Consulta: todo lo que hizo una cuenta mientras tuvo un rol

```sql
SELECT l.fecha_hora, a.operacion, l.tabla_afectada, l.antiguo_valor, l.nuevo_valor
FROM log l
JOIN accion a ON a.id_accion = l.id_accion
JOIN usuario_rol ur ON ur.id_usuario = l.id_usuario
                   AND l.fecha_hora >= ur.fecha_inicio
                   AND (ur.fecha_fin IS NULL OR l.fecha_hora < ur.fecha_fin)
JOIN rol r ON r.id_rol = ur.id_rol
JOIN usuario u ON u.id_usuario = l.id_usuario
WHERE u.cod_sis = :cod_sis
  AND r.nombre_rol = 'Administrador'
ORDER BY l.fecha_hora;
```

## Consulta: qué permisos tenía un rol en una fecha

```sql
SELECT p.nombre_permiso, pr.fecha_inicio, pr.fecha_fin
FROM permiso_rol pr
JOIN permiso p ON p.id_permiso = pr.id_permiso
JOIN rol r ON r.id_rol = pr.id_rol
WHERE r.nombre_rol = 'Docente'
  AND pr.fecha_inicio <= :fecha
  AND (pr.fecha_fin IS NULL OR pr.fecha_fin > :fecha)
ORDER BY p.nombre_permiso;
```

## Pendiente de decidir

- `permiso_rol.estado` se conserva por compatibilidad; con las fechas ya no es necesario para saber si
  una fila vale. Queda abierto si se elimina en otro RNF.
