---
paths:
  - "backend/**/*.php"
---

# Estándares de código — Backend (PHP / Laravel)

## Idioma

- **Identificadores propios de la app** (clases, métodos, funciones, variables, constantes, enums): en inglés.
- **Todo lo relacionado a la base de datos** (tablas, columnas, claves foráneas y sus referencias en queries/modelos/migraciones/relaciones): en español, tal como está definido en la estructura de la BD. No traducir.
- **Métodos propios del framework** (`where()`, `select()`, `with()`, `belongsTo()`, etc.): mantienen su nombre original, no se tocan.
- **Rutas API**: kebab-case; los términos del dominio pueden ir en español (ej. `/api/docente/materias`).
- **Letra "ñ" en identificadores técnicos** (tablas, columnas, rutas y demás referencias en código): reemplazar por `ni`. Esta regla NO aplica a comentarios — ahí se escribe correctamente en español.
- **Comentarios y encabezados de documentación**: siempre en español.

## Estilo base: PSR-12
Vía StyleCI (`backend/.styleci.yml`).
- Indentación de 4 espacios, nunca tabulaciones.
- Líneas de máximo 120 caracteres (recomendado 80).
- Llave de apertura de clases y métodos en línea nueva; en estructuras de control, en la misma línea.
- Un solo statement por línea; un solo `use` por línea de import.
- Espacio después de comas y antes/después de operadores binarios.

Correr el linter (PHP CodeSniffer / StyleCI) antes de cada commit.

## Convenciones de nombres

| Elemento | Convención | Ejemplo |
|---|---|---|
| Clases | PascalCase, normalmente inglés | `Subject`, `SubjectController` |
| Métodos y funciones | camelCase, inglés, verbo + sustantivo | `getSubjectsByTeacher()` |
| Variables | camelCase, inglés | `$selectedSubject` |
| Tablas de BD | snake_case, plural, español | `materias`, `docentes_materias` |
| Columnas | snake_case, español | `fecha_creacion`, `esta_activa`, `anio` |
| Rutas API | kebab-case; dominio puede ir en español | `/api/docente/materias` |
| Migraciones | snake_case; referencia a la tabla en español | `create_materias_table` |
| Constantes / Enums | SCREAMING_SNAKE_CASE, normalmente inglés | `TEACHER_ROLE` |

Ejemplo combinando ambos idiomas correctamente:
```php
$selectedSubject = DB::table('materias')
    ->where('anio', $currentYear)
    ->where('esta_activa', true)
    ->first();
```

## Estructura y capas
- Controllers delgados: reciben el Request, delegan a Services/Actions, devuelven la respuesta.
- Lógica de negocio en `app/Services/`, nunca en Controller ni Modelo.
- Validación siempre vía Form Requests (`php artisan make:request`), nunca manual dispersa.
- Relaciones Eloquent tipadas con return type, camelCase (`groups()`, `teachers()`).
- Un Controller de API por recurso, en `app/Http/Controllers/Api`.
- Rutas nuevas agrupadas por prefijo/rol en `routes/api.php`, con `auth:sanctum` donde corresponda.

## Comentarios y documentación

Los archivos relevantes llevan solo un encabezado breve (en español) explicando su propósito. Se puede omitir en: archivos repetitivos, código genérico, archivos autogenerados, y componentes cuya función es evidente por su estructura/nombre.

```php
/**
 * Gestiona la consulta y asignación de materias disponibles para los docentes.
 *
 * @param  Request  $request
 * @return JsonResponse
 */
class SubjectController extends Controller
{
    // ...
}
```

Minimizar comentarios inline al máximo. No comentar instrucciones evidentes ni describir línea por línea lo que hace una función. Usarlos solo para:
1. Una decisión de negocio que no resulta evidente.
2. Una validación o restricción especial.
3. Una solución temporal o incompatibilidad conocida.
4. Un procedimiento complejo que no puede simplificarse con nombres claros.

Válido:
```php
// Se excluyen las materias archivadas aunque todavía tengan docentes asignados.
$activeSubjects = $subjects->where('esta_archivada', false);
```

Evitar:
```php
// Se obtienen todas las materias
$subjects = Subject::all();
```

## Buenas prácticas obligatorias
- Nunca N+1: usar `with()` / `withCount()`.
- Mass assignment protegido: siempre `$fillable`, nunca `$guarded = []`.
- Respuesta de API consistente: `{ data, mensaje }` o `{ data, meta }`.
- Nunca exponer stacktraces en producción (`APP_DEBUG=false`).
- Cada Historia de Usuario con backend incluye al menos un test (`Tests\Feature`) que cubra sus criterios de aceptación.