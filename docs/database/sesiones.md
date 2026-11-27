# Registro de sesiones

Cada inicio de sesión correcto abre una fila en `sesion`: quién entró, desde qué IP y con qué navegador o
dispositivo (User-Agent). La fila se cierra cuando la sesión termina y **nunca se borra**.

## Tabla `sesion`

| Columna | Qué guarda |
|---|---|
| `id_sesion`, `id_usuario` | PK compuesta (se conserva la del modelo original). |
| `id_token` | Token de Sanctum de la sesión. Queda nulo cuando el token se borra. |
| `ip_direccion` | IP desde la que se inició sesión. |
| `agente` | Navegador o dispositivo (User-Agent), hasta 255 caracteres. |
| `hora_ingreso` | Inicio de la sesión. |
| `hora_salida`, `motivo_salida` | Se llenan juntos al cerrar. |

| `motivo_salida` | Cuándo | Quién firma en la bitácora |
|---|---|---|
| `CIERRE` | La persona pulsa «Cerrar sesión». | La persona. |
| `REVOCADA` | La persona cambia su contraseña (se cierran sus **otras** sesiones). | La persona. |
| `REVOCADA` | La cuenta fue deshabilitada y vuelve a hacer una petición. | El sistema. |
| `EXPIRADA` | Venció el token (12 horas). Lo cierra la tarea horaria `close-expired-sessions`; `hora_salida` es la hora del vencimiento. | El sistema. |

Cada apertura y cada cierre quedan en `log` con las acciones `INICIO_SESION` y `CIERRE_SESION` (las siembra
`ActionSeeder`).

## Sesión en cada operación de la bitácora

**Forma 2: la sesión va en su propia columna.** `log.id_sesion` (nullable) con una clave foránea compuesta
`(id_sesion, id_usuario)` hacia la PK de `sesion`. `AuditLogService` la llena en cada fila escrita durante
una petición con token propio.

- La FK compuesta asegura que la sesión exista **y** que sea de la misma cuenta que firmó la operación.
- Tareas programadas, seeders y comandos dejan la columna en nulo (la FK no se comprueba con un nulo).
- `nuevo_valor` y `antiguo_valor` no cambian: solo tienen datos del registro.
- El índice parcial `idx_log_sesion` sirve para buscar por sesión.

## Consulta: todo lo que hizo una cuenta, con el dispositivo

```sql
SELECT l.fecha_hora, a.operacion, l.tabla_afectada, s.agente, s.ip_direccion
FROM log l
JOIN accion a ON a.id_accion = l.id_accion
JOIN usuario u ON u.id_usuario = l.id_usuario
LEFT JOIN sesion s ON s.id_sesion = l.id_sesion AND s.id_usuario = l.id_usuario
WHERE u.cod_sis = :cod_sis
ORDER BY l.fecha_hora;
```

## Consulta: todo lo que se hizo desde una sesión

```sql
SELECT l.fecha_hora, a.operacion, l.tabla_afectada, l.antiguo_valor, l.nuevo_valor
FROM log l
JOIN accion a ON a.id_accion = l.id_accion
WHERE l.id_sesion = :id_sesion
ORDER BY l.fecha_hora;
```

## Consulta: sesiones abiertas de una cuenta

```sql
SELECT s.id_sesion, s.ip_direccion, s.agente, s.hora_ingreso
FROM sesion s
JOIN usuario u ON u.id_usuario = s.id_usuario
WHERE u.cod_sis = :cod_sis
  AND s.hora_salida IS NULL
ORDER BY s.hora_ingreso DESC;
```

## Modo sin conexión (IndexedDB), cuando se implemente

- Al sincronizar, cada operación debe enviar la **hora real** en que se hizo, no la de la sincronización.
- Si el token venció mientras no había conexión, hay que volver a iniciar sesión antes de sincronizar: lo
  sincronizado queda en la sesión nueva de ese dispositivo.
- La sesión registrada es la del dispositivo que sincroniza.
- Hay que evitar duplicados si un envío se repite (un identificador por operación).

## Pendiente de decidir

- Cuánto tiempo se guardan la IP y el navegador (política de retención).
- Número del RNF de este registro.
