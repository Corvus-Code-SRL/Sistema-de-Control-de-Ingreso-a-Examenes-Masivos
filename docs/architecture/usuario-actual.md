# Usuario actual y código SIS (RNF-02, fase 0)

Esta página fija dos decisiones que la autenticación real (RNF-02, fases 1 a 3) da por hechas.

## 1. Quién ejecuta la operación

Hay **un solo punto** que responde «¿quién es el usuario actual?»: `App\Support\CurrentUser`.

| Método | Devuelve hoy | Lo usan |
|---|---|---|
| `id()` | `auth()->id()` y, si no hay sesión, `sciem.usuario_prueba` | Security, bitácora, Administrador |
| `teacherId()` | `sciem.docente_fijo_id` | Academic, Exams |

Son dos métodos porque hoy el sistema simula **dos personas distintas** (la cuenta de prueba y el docente fijo). Cuando llegue la autenticación, el cambio es el cuerpo de esos dos métodos y nada más: `config('sciem.docente_fijo_id')` y `config('sciem.usuario_prueba')` no se leen en ningún otro lugar del código.

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
| Controller, comando de consola, seeder | Sí |
| Form Request y Policy (capa HTTP, autorizan antes del Controller) | Sí |
| **Service** (`app/Services/**`) | **No** — recibe el id por parámetro |
| Modelo, Resource, `app/Support/**` | No |

La regla la vigila `tests/Unit/Architecture/ServicesDoNotResolveCurrentUserTest.php`: falla si un archivo de `app/Services/` contiene `auth(`, `Auth::`, `->user()`, `CurrentUser` o las dos claves de configuración.

### Cómo controlan las pruebas quién actúa

- Llamar al Service directamente: pasar el id (`$service->create($data, $this->docenteId)`).
- Pasar por HTTP: `$this->actAsTeacher($id)` (docente) o `$this->actAsUserId($id)` (cuenta general). Instalan `Tests\Support\FakeCurrentUser` en el contenedor; lo que la prueba no fija lo sigue resolviendo el resolver real, así que `actingAs()` convive con ellos.
- **No** usar `config()->set('sciem.docente_fijo_id', ...)` ni `config()->set('sciem.usuario_prueba', ...)` en las pruebas.

### Bitácora

`AuditLogService::registrar()` exige el id del autor (último parámetro, obligatorio). Ya no existe un respaldo silencioso a la cuenta de prueba: el Controller decide quién firma el registro.

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
