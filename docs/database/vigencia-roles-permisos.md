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
