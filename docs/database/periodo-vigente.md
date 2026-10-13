# Gestión, período vigente y oferta académica

Desde las migraciones `2026_10_12_000001_normalize_gestion_and_periodo`,
`2026_10_12_000002_create_oferta_academica` y `2026_10_12_000003_rename_activo_to_vigente` el año
académico, el período y lo que se dicta en cada uno están normalizados:

| Tabla | Qué es | Notas |
|---|---|---|
| `gestion` | El año académico (`anio`) | Único, entre 2000 y 2100. |
| `periodo` | Catálogo fijo: `0` Anual, `1` Semestre 1, `2` Semestre 2, `3` Verano, `4` Invierno | `codigo` es el número con el que la UMSS escribe el período. No se edita desde la aplicación. |
| `gestion_periodo` | La instancia (**1/2026**, **2/2026**, **3/2026**…) | Es la antigua tabla `periodo`, renombrada: conserva filas e ids. `vigente` marca el período en curso; un índice parcial permite **a lo sumo uno**. |
| `oferta_academica` | Los pares materia-carrera que se dictan en un período | Clave `(id_carrera, id_materia, id_gestion_periodo)`. El catálogo (`materia`, `materia_carrera`) es permanente; una materia que no se ofrece un período no tiene fila ahí y puede ofertarse de nuevo después. |
| `grupo` | Pasa a `id_gestion_periodo` y **pierde** `gestion` | Una clave compuesta a `oferta_academica` impide registrar un grupo en un período donde su materia no se ofrece. |
| `convocatoria_admision` | Pasa a `id_gestion_periodo` y pierde `gestion` | Módulo Admisiones, independiente del Académico. |

## `estado` y `vigente` no significan lo mismo

| Columna | Qué dice | Valores |
|---|---|---|
| `estado` | El **registro** está habilitado o deshabilitado. Es la misma convención de todas las tablas (`gestion`, `gestion_periodo`, `materia`, `carrera`, `oferta_academica`…). | `ACTIVO` / `INACTIVO` |
| `vigente` | Cuál es **el** período en curso. Hay uno solo en todo el sistema. | `true` en una fila; `false` en las demás |

Un período puede estar `ACTIVO` (habilitado, se puede elegir para registrar grupos de un semestre que
ya pasó) sin ser el `vigente`. Antes la columna se llamaba `activo` y se confundía con `estado`.

## Etiqueta y nombre en la API

En toda la interfaz el período se escribe con la forma estándar de la UMSS: **`{codigo}/{anio}`**, para
todos los períodos (`0/2026` Anual, `1/2026`, `2/2026`, `3/2026` Verano, `4/2026` Invierno).

| Campo (`GET /api/periodos` y `periodo` de un grupo) | Contenido | Ejemplo |
|---|---|---|
| `id_periodo` | `gestion_periodo.id_gestion_periodo` | `2` |
| `etiqueta` | Forma estándar `{codigo}/{anio}`: es la que se muestra | `1/2026` |
| `nombre_periodo` | Nombre del catálogo (texto secundario, p. ej. un *tooltip*) | `Semestre 1` |
| `gestion` | Año de la gestión | `2026` |
| `vigente` | Es el período en curso (solo en `GET /api/periodos`) | `true` |

El frontend **no construye etiquetas**: muestra `etiqueta` y, como mucho, `nombre_periodo` como ayuda.

> **Deuda para el Sprint 4.** Las claves de `meta` conservan el nombre histórico: `id_periodo_activo`,
> `es_periodo_activo` y `nombre_periodo_activo` (esta última lleva la **etiqueta**, `2/2026`). Hay que
> renombrarlas a `…_vigente` / `etiqueta_periodo_vigente` junto con el frontend. Mientras tanto
> `SubjectCatalogService::activePeriodId()` y `ActivePeriodNotConfiguredException` mantienen su nombre.

## El período vigente ya no se configura por entorno

`SCIEM_PERIODO_ACTIVO_ID` **desaparece** de `.env.example` y de `config/sciem.php`. El período vigente
es la fila de `gestion_periodo` con `vigente = true`, que lee `SubjectCatalogService::activePeriodId()`.
Si ninguna lo es, la API responde 500 con *«No hay un período académico vigente…»*
(`GET /api/periodos` sigue funcionando y devuelve `meta.id_periodo_activo = null`).

La migración `2026_10_12_000003` **no depende** de esa variable ni de ninguna otra: solo renombra una
columna y un índice, y no modifica `gestion_periodo` ni `oferta_academica`. La variable solo la leyó,
una única vez, la migración `…000001` para fijar el período vigente de una base que ya tenía datos.

Todavía no hay una pantalla para administrar gestiones y períodos (será una historia aparte). Mientras
tanto se gestionan con SQL, `tinker` o un seeder.

### Fijar el período vigente (base compartida)

Un solo `UPDATE` no alcanza: el índice parcial no admite dos filas vigentes ni siquiera por un instante,
así que se apaga el anterior y se enciende el nuevo **dentro de una transacción**.

```sql
BEGIN;
UPDATE gestion_periodo SET vigente = false WHERE vigente;
UPDATE gestion_periodo SET vigente = true  WHERE id_gestion_periodo = 3;   -- el período que arranca
COMMIT;

SELECT id_gestion_periodo FROM gestion_periodo WHERE vigente;               -- debe devolver una sola fila
```

Con `tinker` es lo mismo:

```bash
php artisan tinker --execute="
DB::transaction(function () {
    DB::table('gestion_periodo')->where('vigente', true)->update(['vigente' => false]);
    DB::table('gestion_periodo')->where('id_gestion_periodo', 3)->update(['vigente' => true]);
});
"
```

### Crear la gestión y el período del próximo semestre

```bash
php artisan tinker --execute="
\$year = App\Models\AcademicYear::firstOrCreate(['anio' => 2027], ['estado' => 'ACTIVO']);
\$type = App\Models\PeriodType::where('codigo', 1)->first();   // 0 Anual, 1, 2, 3 Verano, 4 Invierno
App\Models\Period::create([
    'id_gestion' => \$year->id_gestion,
    'id_periodo' => \$type->id_periodo,
    'fecha_inicio' => '2027-02-01',   // opcional
    'fecha_fin' => '2027-06-30',      // opcional
    'vigente' => false,
    'estado' => 'ACTIVO',
]);
"
```

### Ofertar materias en un período

Una materia aparece en «Materias» del docente, y admite grupos, solo si tiene una oferta ACTIVA en el
período vigente (o en el que se elija al registrar el grupo). Para ofertar en el período `3` todos los
pares activos de una carrera, de forma idempotente:

```sql
INSERT INTO oferta_academica (id_carrera, id_materia, id_gestion_periodo)
SELECT id_carrera, id_materia, 3
FROM materia_carrera
WHERE estado = 'ACTIVO' AND id_carrera = 1
ON CONFLICT DO NOTHING;
```

Una materia que deja de dictarse simplemente no recibe oferta en el período siguiente; los grupos que
ya tuvo siguen ligados a la oferta de su período.

## Migrar una base que ya tiene datos

Antes de empezar, respaldar la base (`pg_dump`). La base compartida ya tiene aplicadas las tres
migraciones; esta sección es para otra base con la forma anterior.

1. Si todavía no tiene la `…000001`: quien migra define en **su** `backend/.env`
   `SCIEM_PERIODO_ACTIVO_ID=<id del período en curso>` y corre `php artisan config:clear` (con la
   configuración en caché `env()` no ve el `.env`). Es la única migración que lo lee.
2. `php artisan migrate --pretend` para ver las sentencias sin ejecutarlas.
3. `php artisan migrate`. Cada migración corre en una transacción: si algo no encaja, **no se modifica
   nada** y el mensaje lista las filas culpables (nombre de período que no es `N-AAAA` ni `AAAA-N`,
   año del nombre distinto de `gestion`, año fuera de 2000-2100, dos períodos que resuelven al mismo
   `(año, período)`, o grupos que chocarían en `unq_grupo` al quitar `grupo.gestion`).
4. Verificar (ver abajo) y **borrar `SCIEM_PERIODO_ACTIVO_ID` del `.env`**.

### Qué hace cada migración

| Migración | Efecto |
|---|---|
| `2026_10_12_000001_normalize_gestion_and_periodo` | Verifica los datos (aborta si algo no encaja). Renombra `periodo` → `gestion_periodo` y `id_periodo` → `id_gestion_periodo` (sus ids y las FK de `grupo` y `convocatoria_admision` se conservan). Crea `gestion` con los años distintos y el catálogo `periodo` (5 filas). Rellena `id_gestion` e `id_periodo` de cada fila a partir de `nombre_periodo` y elimina `nombre_periodo` y `gestion`. Agrega `fecha_inicio`, `fecha_fin`, `activo`, `estado` y el índice parcial de un solo período. Fija como `activo` el período de `SCIEM_PERIODO_ACTIVO_ID`. Renombra `grupo.id_periodo` y `convocatoria_admision.id_periodo` a `id_gestion_periodo` y elimina `convocatoria_admision.gestion`. |
| `2026_10_12_000002_create_oferta_academica` | Crea `oferta_academica`. Rellena una oferta ACTIVA en el período activo por cada par `materia_carrera` ACTIVO, y una oferta por cada par ya usado por un grupo en el período de ese grupo (con el estado del par). Elimina `grupo.gestion` y `fk_grupo_materia_carrera`; `unq_grupo` pasa a `(id_carrera, id_materia, num_grupo, id_gestion_periodo)` y se agrega la clave compuesta de `grupo` a `oferta_academica`. |
| `2026_10_12_000003_rename_activo_to_vigente` | Renombra `gestion_periodo.activo` → `vigente` y su índice parcial `unq_gestion_periodo_activo` → `unq_gestion_periodo_vigente`; los valores se conservan. Actualiza `periodo.nombre`: `1` → `Semestre 1`, `2` → `Semestre 2` (por `codigo`). Idempotente. No toca `gestion_periodo` ni `oferta_academica` ni depende de variables de entorno. |

Las tres tienen `down()`: restauran la forma anterior. Lo único que se pierde al revertir `…000001` y
`…000002` son las ofertas, las fechas y el indicador de período vigente.

### Verificación

```sql
SELECT gp.id_gestion_periodo, g.anio, p.codigo, p.nombre, gp.vigente, gp.estado
FROM gestion_periodo gp
JOIN gestion g ON g.id_gestion = gp.id_gestion
JOIN periodo p ON p.id_periodo = gp.id_periodo
ORDER BY gp.id_gestion_periodo;                          -- ids conservados; un solo vigente

SELECT count(*) FROM grupo;                              -- igual que antes de migrar
SELECT count(*) FROM oferta_academica;                   -- pares activos + pares con grupos
```

Después, `GET /api/periodos` debe listar los períodos con `etiqueta` (`1/2026`, `2/2026`),
`nombre_periodo` (`Semestre 1`, `Semestre 2`) y `vigente`, y «Materias» del docente debe mostrar los
pares ofertados en el período vigente.
