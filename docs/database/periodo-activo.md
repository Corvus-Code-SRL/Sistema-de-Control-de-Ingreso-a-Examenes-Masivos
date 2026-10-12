# Gestión, período y oferta académica

Desde las migraciones `2026_10_12_000001_normalize_gestion_and_periodo` y
`2026_10_12_000002_create_oferta_academica` el año académico, el período y lo que se dicta en cada
uno están normalizados:

| Tabla | Qué es | Notas |
|---|---|---|
| `gestion` | El año académico (`anio`) | Único, entre 2000 y 2100. |
| `periodo` | Catálogo fijo: `0` Anual, `1`, `2`, `3` Verano, `4` Invierno | No se edita desde la aplicación. |
| `gestion_periodo` | La instancia (**1/2026**, **2/2026**, **Verano 2026**…) | Es la antigua tabla `periodo`, renombrada: conserva filas e ids. `activo` marca el período vigente; un índice parcial permite **a lo sumo uno**. |
| `oferta_academica` | Los pares materia-carrera que se dictan en un período | Clave `(id_carrera, id_materia, id_gestion_periodo)`. El catálogo (`materia`, `materia_carrera`) es permanente; una materia que no se ofrece un período no tiene fila ahí y puede ofertarse de nuevo después. |
| `grupo` | Pasa a `id_gestion_periodo` y **pierde** `gestion` | Una clave compuesta a `oferta_academica` impide registrar un grupo en un período donde su materia no se ofrece. |
| `convocatoria_admision` | Pasa a `id_gestion_periodo` y pierde `gestion` | Módulo Admisiones, independiente del Académico. |

La API sigue hablando de «período»: `id_periodo` es el `id_gestion_periodo`, y `nombre_periodo` su
etiqueta (`1/2026`, `2/2026`; `Anual 2026`, `Verano 2026`, `Invierno 2026`).

## El período activo ya no se configura por entorno

`SCIEM_PERIODO_ACTIVO_ID` **desaparece** de `.env.example` y de `config/sciem.php`. El período vigente
es la fila de `gestion_periodo` con `activo = true`, que lee `SubjectCatalogService::activePeriodId()`.
Si ninguna está activa, la API responde 500 con *«No hay un período académico activo…»*
(`GET /api/periodos` sigue funcionando y devuelve `meta.id_periodo_activo = null`).

Todavía no hay una pantalla para administrar gestiones y períodos (será una historia aparte). Mientras
tanto se gestionan con `tinker` o un seeder.

### Activar un período existente

Un solo período puede estar activo: se desactiva el anterior y se activa el nuevo en la misma operación.

```bash
php artisan tinker --execute="
DB::transaction(function () {
    DB::table('gestion_periodo')->where('activo', true)->update(['activo' => false]);
    DB::table('gestion_periodo')->where('id_gestion_periodo', 3)->update(['activo' => true]);
});
"
```

### Crear la gestión y el período del próximo semestre

```bash
php artisan tinker --execute="
\$year = App\Models\AcademicYear::firstOrCreate(['anio' => 2027], ['estado' => 'ACTIVO']);
\$type = App\Models\PeriodType::where('codigo', 1)->first();   // 1, 2, 0 Anual, 3 Verano, 4 Invierno
App\Models\Period::create([
    'id_gestion' => \$year->id_gestion,
    'id_periodo' => \$type->id_periodo,
    'fecha_inicio' => '2027-02-01',   // opcional
    'fecha_fin' => '2027-06-30',      // opcional
    'activo' => false,
    'estado' => 'ACTIVO',
]);
"
```

### Ofertar materias en un período

Una materia aparece en «Materias» del docente, y admite grupos, solo si tiene una oferta ACTIVA en el
período. Para ofertar en el período `3` todos los pares activos de una carrera:

```sql
INSERT INTO oferta_academica (id_carrera, id_materia, id_gestion_periodo)
SELECT id_carrera, id_materia, 3
FROM materia_carrera
WHERE estado = 'ACTIVO' AND id_carrera = 1
ON CONFLICT DO NOTHING;
```

Una materia que deja de dictarse simplemente no recibe oferta en el período siguiente; los grupos que
ya tuvo siguen ligados a la oferta de su período.

## Migrar una base que ya tiene datos (la compartida)

Ensayado en `sciem_test` con datos de la misma forma que la base compartida (`periodo` con `1-2026` y
`2-2026`, grupos con `id_periodo = 2`); la base compartida en sí no se tocó. **Antes de empezar, respaldar
la base** (`pg_dump`).

1. Quien corre la migración define en **su** `backend/.env` el id del período vigente
   (`SCIEM_PERIODO_ACTIVO_ID=2` en la base compartida). Es la única vez que se usa la variable.
2. Limpiar la configuración en caché: `php artisan config:clear` (con la configuración en caché,
   `env()` no ve el `.env` y ningún período quedaría activo).
3. `php artisan migrate --pretend` para ver las sentencias sin ejecutarlas.
4. `php artisan migrate`. Cada migración corre en una transacción: si algo no encaja, **no se modifica
   nada** y el mensaje lista las filas culpables (nombre de período que no es `N-AAAA` ni `AAAA-N`, año
   del nombre distinto de `gestion`, año fuera de 2000-2100, dos períodos que resuelven al mismo
   `(año, período)`, o grupos que chocarían en `unq_grupo` al quitar `grupo.gestion`).
5. Verificar (ver abajo) y **borrar `SCIEM_PERIODO_ACTIVO_ID` del `.env`**.

Sin la variable (o con un id que no existe) la migración termina igual, deja **ningún período activo** y
lo avisa en el log; se activa uno después con el comando de arriba.

### Qué hace cada migración

| Migración | Efecto |
|---|---|
| `2026_10_12_000001_normalize_gestion_and_periodo` | Verifica los datos (aborta si algo no encaja). Renombra `periodo` → `gestion_periodo` y `id_periodo` → `id_gestion_periodo` (sus ids y las FK de `grupo` y `convocatoria_admision` se conservan). Crea `gestion` con los años distintos y el catálogo `periodo` (5 filas). Rellena `id_gestion` e `id_periodo` de cada fila a partir de `nombre_periodo` y elimina `nombre_periodo` y `gestion`. Agrega `fecha_inicio`, `fecha_fin`, `activo`, `estado` y el índice parcial de un solo activo. Activa el período de `SCIEM_PERIODO_ACTIVO_ID`. Renombra `grupo.id_periodo` y `convocatoria_admision.id_periodo` a `id_gestion_periodo` y elimina `convocatoria_admision.gestion`. |
| `2026_10_12_000002_create_oferta_academica` | Crea `oferta_academica`. Rellena una oferta ACTIVA en el período activo por cada par `materia_carrera` ACTIVO, y una oferta por cada par ya usado por un grupo en el período de ese grupo (con el estado del par). Elimina `grupo.gestion` y `fk_grupo_materia_carrera`; `unq_grupo` pasa a `(id_carrera, id_materia, num_grupo, id_gestion_periodo)` y se agrega la clave compuesta de `grupo` a `oferta_academica`. |

Ambas tienen `down()`: restauran la forma anterior (`nombre_periodo` como `N-AAAA`, `gestion`,
`grupo.gestion` desde el año). Lo único que se pierde al revertir son las ofertas, las fechas y el
indicador `activo`.

### Verificación

```sql
SELECT gp.id_gestion_periodo, g.anio, p.codigo, p.nombre, gp.activo
FROM gestion_periodo gp
JOIN gestion g ON g.id_gestion = gp.id_gestion
JOIN periodo p ON p.id_periodo = gp.id_periodo
ORDER BY gp.id_gestion_periodo;                          -- ids conservados; uno solo activo

SELECT count(*) FROM grupo;                              -- igual que antes de migrar
SELECT count(*) FROM oferta_academica;                   -- pares activos + pares con grupos
```

Después, `GET /api/periodos` debe listar los períodos con su etiqueta (`1/2026`, `2/2026`) y el
`activo`, y «Materias» del docente debe mostrar los pares ofertados en el período activo.
