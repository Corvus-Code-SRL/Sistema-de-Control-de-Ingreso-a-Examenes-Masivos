# Usuario actual y código SIS (RNF-02)

Esta página fija dos decisiones de la autenticación real (RNF-02).

## 1. Quién ejecuta la operación

Hay **un solo punto** que responde «¿quién ejecuta la operación?» en la API: `App\Support\CurrentUser`, que devuelve **la cuenta autenticada por el token de Sanctum**. No hay respaldo: sin sesión devuelve `null` (`id()`) o `''` (`teacherId()`), y como toda ruta salvo el login exige `auth:sanctum`, un Controller nunca lo ve vacío.

| Método | Devuelve | Lo usan |
|---|---|---|
| `id()` | `auth()->id()` (o `null`) | Security, bitácora, Administrador |
| `teacherId()` | lo mismo, como `string` | Academic, Exams |

Son la misma cuenta; el rol lo comprueba cada Form Request con `UserRoleService::isTeacher()` / `isAdministrator()` (rol vigente **y** cuenta ACTIVA), no este resolver.

### Seeders y comandos: `SystemActor`

Sin sesión no hay a quién atribuir una escritura, pero `log.id_usuario` es NOT NULL. Los seeders y comandos usan `App\Support\SystemActor` (la cuenta de `config('sciem.usuario_prueba')`, que `UserSeeder` siembra). **Solo ellos**: `SystemActorOnlyForSeedersTest` falla si un Controller, Request, Policy o Service lo usa, o si un seeder usa `CurrentUser`.

> **Riesgo conocido (Sprint 3):** esa cuenta se siembra con SIS `000000000`, contraseña `password` y rol Administrador. Mientras exista, es una puerta de entrada conocida en cualquier entorno compartido.

### Regla: los Services no resuelven al usuario

> Un Service **no** llama a `auth()`, a `Auth::`, a `$request->user()` ni a `CurrentUser`. Recibe al usuario como **argumento explícito** que le entrega el Controller.

```php
// Controller
$exam = $this->examService->create($request->validated(), $this->currentUser->teacherId());

// Service
public function create(array $data, string $teacherId): Exam
```

**Por qué.** Si un Service lee la sesión, una prueba ya no puede decidir quién actúa: depende de la configuración global o del estado de `auth()`. Con el usuario como argumento, una prueba llama al Service con el id que quiera.

| Capa | ¿Puede usar `CurrentUser`? |
|---|---|
| Controller | Sí |
| Comando de consola, seeder | No: usan `SystemActor` |
| Form Request y Policy (capa HTTP, autorizan antes del Controller) | Sí |
| **Service** (`app/Services/**`) | **No** — recibe el id por parámetro |
| Modelo, Resource, `app/Support/**` | No |

La regla la vigila `tests/Unit/Architecture/ServicesDoNotResolveCurrentUserTest.php`: falla si un archivo de `app/Services/` contiene `auth(`, `Auth::`, `->user()`, `CurrentUser`, `SystemActor` o `sciem.usuario_prueba`.

### Cómo controlan las pruebas quién actúa

- Llamar al Service directamente: pasar el id (`$service->create($data, $this->docenteId)`).
- Pasar por HTTP: `$this->actAsTeacher($id)` o `$this->actAsUserId($id)` (y `actingAs()`), que autentican con `Sanctum::actingAs` como un token real. La cuenta debe existir y tener en la base el rol que el endpoint exige (`seedAcademicCatalog()` da el rol Docente a sus dos docentes; `seedSecurityAccounts()` deja al Administrador como actor). `actAsGuest()` descarta la sesión para probar el 401.

### Bitácora

`AuditLogService::registrar()` exige el id del autor (último parámetro, obligatorio). No hay respaldo silencioso: el Controller firma con la cuenta autenticada y los seeders con `SystemActor`.

## 2. Código SIS: una sola forma canónica

`App\Support\SisCode::normalize()` es la única definición. **Recorta** los bordes, **pasa a mayúsculas** y **colapsa los espacios internos** (incluido el espacio duro que deja Excel). Nada más:

- **No** quita ceros a la izquierda.
- **No** exige solo dígitos.
- **No** exige una longitud.

Conviven tres formatos y los tres son válidos: 5 dígitos (docentes, `10452`), 9 dígitos (auxiliares y estudiantes, `201800451`) y alfanumérico (Administrador, `ADM0001`). Las reglas propias de un flujo —por ejemplo «solo dígitos, 8 a 12» en la carga de nómina de estudiantes— se evalúan **sobre el valor ya normalizado** y no se reutilizan fuera de ese flujo.

**Por qué importa.** `usuario.cod_sis` y `estudiante.cod_sis` son la clave que une cuentas con filas de la nómina; HU-08 depende de esa unión para impedir que un estudiante sea habilitado como auxiliar de su propio examen. Si un lado guardara `adm0001` y el otro `ADM0001`, la comparación fallaría sin avisar. Las constraints `UNIQUE` de la base distinguen mayúsculas, así que la base no corrige esto por sí sola.

### Dónde se aplica

| Lado | Punto |
|---|---|
| `usuario` | `StoreUserRequest::prepareForValidation()` (antes de la regla `unique`), `UserService::registrar()` y `verifySisCode()`, mutador `User::setCodSisAttribute()` |
| `estudiante` | `StudentRosterRow` (toda la carga de nómina: análisis, coincidencia con la base y alta), `StudentRosterDatabaseMatcher` (clave del lado de la base), mutador `Student::setCodSisAttribute()` |
| Seeders | `UserSeeder`, `AccountTestDataSeeder`, `GroupTestDataSeeder` |

Toda escritura nueva de un `cod_sis` —por Eloquent, por una Request o por un seeder— debe pasar por `SisCode::normalize()`. Una consulta `DB::table(...)` que inserte `cod_sis` evita los mutadores: debe normalizar a mano.
