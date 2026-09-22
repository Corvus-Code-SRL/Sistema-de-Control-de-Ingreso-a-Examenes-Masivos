# SCIEM — Sistema de Control de Ingreso a Exámenes Masivos

Plataforma web para el control de ingreso, la validación de estudiantes y la gestión de incidentes en exámenes universitarios.

Proyecto desarrollado por **Corvus Code S.R.L.** para la Convocatoria Pública **CPTIS-452026-2026**, en el marco de la materia **Taller de Ingeniería de Software (TIS)** de la Universidad Mayor de San Simón (UMSS).

---

## Contenido

- [Funcionalidades principales](#funcionalidades-principales)
- [Arquitectura](#arquitectura)
- [Stack tecnológico](#stack-tecnológico)
- [Estructura del repositorio](#estructura-del-repositorio)
- [Requisitos previos](#requisitos-previos)
- [Instalación y ejecución](#instalación-y-ejecución)
- [Datos de prueba compartidos](#datos-de-prueba-compartidos)
- [Pruebas](#pruebas)
- [Convenciones](#convenciones)
- [Flujo de trabajo](#flujo-de-trabajo)
- [Documentación](#documentación)
- [Empresa](#empresa)

---

## Funcionalidades principales

- **Seguridad:** cuentas de usuario, roles (Administrador, Docente, Auxiliar), permisos y bitácora de operaciones.
- **Gestión académica:** materias, grupos y carga de nóminas de estudiantes (CSV, XLSX y PDF).
- **Exámenes:** creación de exámenes, asignación de grupos y ambientes, invitación a docentes colaboradores y habilitación de auxiliares.
- **Control de ingreso:** punto de control, identificación y validación de estudiantes, registro de tardanzas e intentos no autorizados, en tiempo real.
- **Incidencias:** registro y revisión de faltas durante el examen, con evidencia y trazabilidad.
- **Centrales de riesgo:** historial de antecedentes disciplinarios, con una central académica y otra de admisión independientes entre sí.
- **Admisión** *(fase posterior)*: modalidades, convocatorias y exámenes de admisión.

---

## Arquitectura

SCIEM utiliza un **monolito modular desacoplado** con **MVC + capa de Services**:

- **Frontend:** SPA en React + TypeScript, organizada por *features*.
- **Backend:** API REST en Laravel. Los controladores delegan la lógica de negocio a Services organizados por módulo.
- **Despliegue:** una sola aplicación. Apache sirve el frontend compilado y la API desde el mismo origen.

```mermaid
flowchart LR
    A["React + TypeScript<br/>(SPA)"] -- "HTTP / JSON" --> B["API REST<br/>Laravel"]
    B --> C["FormRequest<br/>(validación)"]
    C --> D["Services<br/>(lógica de negocio)"]
    D --> E["Eloquent ORM"]
    E --> F[("PostgreSQL")]
    D -. "caché" .-> G[("Redis")]
    D --> H["Resources<br/>(respuesta JSON)"]
```

### Módulos

| Módulo backend | Feature(s) frontend | Responsabilidad |
|---|---|---|
| `Security` | `auth`, `users`, `audit-log` | Autenticación, cuentas, roles, permisos y bitácora |
| `Academic` | `subjects`, `groups`, `students` | Materias, grupos y nóminas |
| `Exams` | `exams`, `classrooms`, `assistants` | Exámenes, ambientes, auxiliares y reportes |
| `EntryControl` | `entry-control` | Punto de control y validación de ingreso |
| `Incidents` | `incidents` | Registro y revisión de incidencias |
| `RiskCenter/Academic` | `risk-center/academic` | Central de Riesgo Académica |
| `RiskCenter/Admission` | `risk-center/admission` | Central de Riesgo de Admisión |
| `Admissions` | `admissions` | Procesos de admisión |

### Actualización en tiempo real

Las pantallas de control de ingreso, seguimiento del examen e incidencias se actualizan mediante **consulta periódica** (hook `usePolling`), un mecanismo compatible con la infraestructura de despliegue disponible.

> La justificación completa de la arquitectura se documentará en `docs/architecture/justificacion-arquitectura.md` (pendiente de redactar).

---

## Stack tecnológico

Las versiones están fijadas por compatibilidad con los servidores de despliegue de la UMSS. **No actualizar dependencias sin coordinación previa con el equipo.**

| Capa | Tecnología | Versión |
|---|---|---|
| Backend | PHP | 8.0.30 |
| | Laravel Framework | 8.83.29 |
| | Composer | 2.9.6 |
| | Eloquent ORM | incluido con Laravel |
| Servidor | Apache HTTP Server | 2.4.62 |
| Datos | PostgreSQL | 15.0 |
| | Redis | 7.4.3 |
| Frontend | Node.js | 22.23.2 |
| | npm | 10.9.8 |
| | React / React DOM | 18.3.1 |
| | TypeScript | 5.7.3 |
| | Vite | 6.4.3 |
| | Tailwind CSS / @tailwindcss/vite | 4.3.3 |
| | shadcn CLI | 4.21.0 |
| UI base | shadcn/ui · Radix · Lucide Icons · Preset Nova | — |

Detalle en [`docs/architecture/stack.md`](docs/architecture/stack.md).

---

## Estructura del repositorio

> La estructura de abajo es la **estructura objetivo** documentada en `docs/architecture/`. El scaffold actual ya cubre la mayor parte (carpetas por módulo en `Http/Controllers`, `Http/Requests`, `Services`, `routes/api/`; `deployment/docker/{apache,postgres,redis}`), pero todavía faltan por crear: el `docker-compose.yml` de despliegue y el `Dockerfile`/`sciem.conf` de Apache, `frontend/.env.example`, la configuración de ESLint del frontend, y las traducciones en `backend/resources/lang/es/`.

```text
.
├── backend/                    # API REST (Laravel 8)
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/    # Por módulo
│   │   │   ├── Requests/       # Validación, por módulo
│   │   │   ├── Resources/      # Formato de respuesta, por módulo
│   │   │   └── Middleware/
│   │   ├── Models/             # Modelos Eloquent
│   │   ├── Policies/           # Autorización
│   │   ├── Services/           # Lógica de negocio, por módulo
│   │   └── Support/            # Utilidades transversales (caché, almacenamiento, respuestas)
│   ├── database/               # Migraciones, factories y seeders
│   ├── routes/
│   │   ├── api.php             # Carga las rutas de cada módulo
│   │   └── api/                # Rutas por módulo
│   └── tests/                  # Feature/ y Unit/Services/, por módulo
├── frontend/                   # SPA (React + TypeScript + Vite)
│   └── src/
│       ├── app/                # App, providers y router
│       ├── components/         # ui/ (shadcn), layout/, common/
│       ├── features/           # Un directorio por funcionalidad
│       ├── hooks/               # Hooks reutilizables
│       ├── lib/                # Cliente HTTP y utilidades
│       ├── types/               # Tipos compartidos
│       └── config/              # Configuración y variables de entorno
├── deployment/
│   └── docker/                 # docker-compose, Apache, PostgreSQL, Redis
└── docs/
    └── architecture/           # Arquitectura, stack y decisiones (ADR)
```

Cada feature del frontend sigue esta plantilla (las subcarpetas se crean solo cuando se necesitan):

```text
features/<feature>/
├── components/
├── hooks/
├── pages/
├── services/
├── types/
└── index.ts        # API pública del feature
```

---

## Requisitos previos

- PHP 8.0 con las extensiones `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json` y `bcmath`
- Extensión `redis` de PHP (o el paquete `predis/predis`, según `REDIS_CLIENT`)
- Composer 2
- Node.js 22 y npm 10
- PostgreSQL 15
- Redis 7
- Docker y Docker Compose *(opcional; permite ejecutar el backend sin instalar PHP, PostgreSQL ni Redis: ver [`deployment/docker/README.md`](deployment/docker/README.md))*

---

## Instalación y ejecución

### 1. Clonar el repositorio

```bash
git clone https://github.com/Corvus-Code-SRL/Sistema-de-Control-de-Ingreso-a-Examenes-Masivos.git
cd Sistema-de-Control-de-Ingreso-a-Examenes-Masivos
git checkout develop
```

### 2. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Configurar la conexión en `.env` (el `.env.example` actual todavía trae los valores por defecto de MySQL — hay que sobrescribirlos):

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sciem
DB_USERNAME=postgres
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Crear las tablas y levantar el servidor:

```bash
php artisan migrate
php artisan serve        # http://localhost:8000
```

> Usar `composer install`, **nunca** `composer update`, para respetar las versiones de `composer.lock`.
> No usar `php artisan migrate:fresh` sobre una base de datos con información que se desee conservar.

### 3. Frontend

```bash
cd frontend
npm ci
npm run dev              # http://localhost:5173
```

En desarrollo, Vite redirige las peticiones de `/api` al backend (`http://localhost:8000`) para simular el mismo origen del despliegue.

> Usar `npm ci`, **nunca** `npm update`, para respetar las versiones de `package-lock.json`.

### 4. Entorno con Docker (opcional)

Para desarrollar sin instalar PHP, PostgreSQL ni Redis en el sistema, el backend puede ejecutarse con Docker Compose. La guía está en [`deployment/docker/README.md`](deployment/docker/README.md).

El despliegue con un solo contenedor Apache (frontend compilado + API en el mismo origen) sigue pendiente: el `docker-compose.yml` de despliegue, el `Dockerfile` de Apache y `sciem.conf` todavía no existen.

---

## Datos de prueba compartidos

> **Datos temporales, solo para desarrollo.** Son inventados: no corresponden a personas reales y el dominio de correo `sciem.test` no existe. Se eliminarán cuando haya datos reales. No los cargue en producción ni en una base compartida.

Todo el equipo trabaja con los mismos registros: cada valor está escrito a mano en `backend/database/seeders/TestData/` (sin Faker), incluidos los ids y los uuid. Cargarlos dos veces no duplica nada: cada tabla se escribe con *upsert* por clave primaria y el historial de roles de las cuentas de prueba se restablece a su estado original.

### Cómo cargarlos

1. Partir de una base creada con `docs/database/creation-script.sql`. Si la base local ya tiene datos armados a mano con los mismos códigos (por ejemplo la carrera `SIS` o el periodo `2-2026` con otro id), el seeder se detiene sin escribir nada y lo explica.
2. En `backend/.env`, `APP_ENV=local` (o `development`) y estos tres valores, que ya trae `.env.example`:

    ```dotenv
    SCIEM_DOCENTE_FIJO_ID=00000000-0000-4000-8000-000000000011
    SCIEM_PERIODO_ACTIVO_ID=9303
    SCIEM_USUARIO_PRUEBA=00000000-0000-4000-8000-000000000001
    ```

3. Ejecutar:

    ```bash
    cd backend
    php artisan config:clear
    php artisan db:seed --class="Database\Seeders\TestData\TestDataSeeder"
    ```

El seeder no está registrado en `DatabaseSeeder`, así que `php artisan db:seed` a secas no lo carga. Se niega a correr con cualquier `APP_ENV` que no sea `local` o `development` y rechaza expresamente la base `sciem_test`, incluso en entorno local. La suite construye su propio esquema y nunca usa estos datos. También ejecuta `RoleSeeder`, `ActionSeeder` y `ExamTypeSeeder`, que son idempotentes.

### Comprobar qué datos ve el frontend

Cambiar `.env.example` no modifica un `.env` existente. La API debe usar el docente `…0011`, el periodo `9303` y la misma base donde se ejecutó el seeder. Después de modificar su `.env`, limpiar la configuración y reiniciar el servidor PHP. Si PHP corre en Docker, ejecutar estos pasos **en el contenedor que atiende el puerto 8000**; un contenedor sin el repositorio montado conserva su propia copia del código y de `.env`.

Para conservar una base antigua, crear otra base local vacía (por ejemplo `sciem_demo`), cargar en ella `docs/database/creation-script.sql` y seleccionar `DB_DATABASE=sciem_demo` en la API antes de sembrar. No ejecutar el script de creación sobre tablas existentes. El esquema de exámenes debe incluir HU-024; el seeder lo comprueba antes de escribir.

La verificación de la carga debe mostrar 8 materias, 15 pares materia-carrera, 11 grupos, 24 estudiantes y 8 exámenes en una base nueva. `GET /api/materias` debe devolver los pares y `GET /api/carreras/9101/materias/9201/grupos` los grupos propios y ajenos de Cálculo I en Sistemas.

**Limitación actual de la aplicación:** `ExamsPage.tsx` inicializa su lista con `[]` y no consulta el backend; tampoco existe `GET /api/examenes`. Por eso «Exámenes programados» seguirá vacío aun con datos cargados. El formulario de creación sí consulta `/api/examenes/formulario`, y los exámenes sembrados permiten probar las advertencias de superposición y las operaciones de edición/cancelación por API. Conectar el listado requiere implementar esa funcionalidad.

`UserSeeder` escribe en el mismo uuid del Administrador de prueba. Si después se corre `php artisan db:seed`, el nombre y el código SIS de esa cuenta vuelven a los de `UserSeeder`; basta con volver a cargar los datos de prueba.

### Cuentas

Todas tienen la contraseña `password` (hash bcrypt fijo). Todavía no hay inicio de sesión: la aplicación actúa como el Docente fijo y el Administrador de prueba que indica `.env`.

| Cuenta | uuid (`usuario.id_usuario`) | cod_sis | Correo | Rol vigente |
| --- | --- | --- | --- | --- |
| Valeria Montaño Ríos | `00000000-0000-4000-8000-000000000001` | `ADM0001` | valeria.montano@sciem.test | Administrador (`SCIEM_USUARIO_PRUEBA`) |
| Marcelo Quiroga Andrade | `00000000-0000-4000-8000-000000000011` | `10452` | marcelo.quiroga@sciem.test | Docente (`SCIEM_DOCENTE_FIJO_ID`); antes Auxiliar |
| Rosario Salazar Vidal | `00000000-0000-4000-8000-000000000012` | `10487` | rosario.salazar@sciem.test | Docente |
| Gustavo Rocha | `00000000-0000-4000-8000-000000000013` | `10533` | gustavo.rocha@sciem.test | Docente (sin apellido materno) |
| Daniela Ferrufino Soliz | `00000000-0000-4000-8000-000000000021` | `201800451` | daniela.ferrufino@sciem.test | Auxiliar |
| Iván Choque Mamani | `00000000-0000-4000-8000-000000000022` | `201900782` | ivan.choque@sciem.test | Auxiliar |
| Lucía Terrazas Paz | `00000000-0000-4000-8000-000000000031` | `202000315` | lucia.terrazas@sciem.test | ninguno |
| Óscar Villarroel Gutiérrez | `00000000-0000-4000-8000-000000000041` | `10398` | oscar.villarroel@sciem.test | ninguno; cuenta INACTIVA con rol Docente cerrado |

Los códigos SIS siguen el formato de cada tipo de cuenta: el Administrador es alfanumérico, los docentes tienen 5 dígitos, y los auxiliares y estudiantes, 9.

### Casos borde

| Caso | Registro de prueba | Historia |
| --- | --- | --- |
| Materia compartida por carreras de facultades distintas | Cálculo I en Sistemas, Informática (FCYT) y Economía (FCE) | HU-016, HU-017 |
| Materia INACTIVA con par activo | Taller de Sistemas Operativos en Sistemas | HU-016 |
| Par INACTIVO con materia activa | Base de Datos I en Informática; su grupo 1 (9409) responde 422 en el detalle | HU-016, HU-017 |
| Par sin grupos del docente fijo | Contabilidad General en Administración (solo Rosario Salazar) | HU-016 |
| Par sin ningún grupo | Microeconomía en Economía | HU-017 |
| Mismo número de grupo, misma materia, otra carrera | Grupo 1 de Cálculo I en Sistemas (9401) y en Economía (9403) | HU-017 |
| Grupos de otro docente en el mismo par | Cálculo I en Sistemas: grupo 1 del docente fijo y grupo 2 de Rosario Salazar (9402), abierto en el listado y con 403 en el detalle | HU-017 |
| Grupo sin nómina | Base de Datos I en Sistemas, grupo 1 (9404) | HU-017 |
| Inscripción retirada, que no cuenta como inscrito | Gabriela Guzmán en el grupo 9401 (INACTIVO) | HU-017 |
| Grupo de un periodo anterior | Cálculo I en Sistemas, grupo 1 del periodo 1-2026 (9410) | HU-017 |
| Cuenta sin rol | Lucía Terrazas | HU-004 |
| Cuenta deshabilitada | Óscar Villarroel | HU-004 |
| Historial de roles de dos tramos | Marcelo Quiroga: Auxiliar → Docente | HU-004 |
| Docente con asignaciones activas | Marcelo Quiroga, Rosario Salazar, Gustavo Rocha | HU-004 |
| Código SIS con cuenta existente | Cualquier cod_sis de la tabla de cuentas, por ejemplo `10452` | HU-001 |
| Código SIS libre y reconocido por el SIS simulado | `202312345`, `202312346`, `202312347`, `201900001` | HU-001 |
| Código SIS no reconocido | `999999999` | HU-001 |
| Ambiente fuera de servicio | Aula `612` (INACTIVO) | HU-024 |
| Tipos de examen Parcial y Final | `tipo_examen` 9701 y 9702 (categoría REGULAR) | HU-024 |
| Exámenes propios y ajenos en el mismo par | 9801 (Marcelo) y 9803 (Rosario), Cálculo I/Sistemas, 15-10-2026 a las 08:00 | HU-024 |
| Misma materia en otra facultad | 9802, Final Cálculo ECO, 20-11-2026 a las 10:00 | HU-024 |
| Examen cancelado, sin reserva efectiva de horario | 9804, Base de Datos I/Sistemas, 16-10-2026 a las 14:00 | HU-024 |
| Estados que bloquean edición y cancelación | 9805 EN_INGRESO, 9806 EN_CURSO, 9807 FINALIZADO | HU-024 |
| Examen que cruza medianoche | 9808, Programación/Sistemas, 17-10-2026 de 23:00 a 01:00 (120 minutos) | HU-024 |

Periodos: `2-2025` (9301), `1-2026` (9302) y `2-2026` (9303, el activo). Hay 24 estudiantes, repartidos entre 2 y 6 por grupo. Los ocho exámenes (9801–9808) tienen un ambiente y un grupo del mismo par cada uno. Sus fechas y estados son fijos: no avanzan con el reloj. Cuando esas fechas queden en el pasado, el formulario de HU-024 rechazará reutilizarlas para crear o editar; actualizar el conjunto compartido de forma coordinada si se necesita otro calendario.

### Cómo retirarlos

Todos los ids numéricos de prueba están en el rango 9000–9999 y todos los uuid empiezan con `00000000-0000-4000-8000-`. Para retirar los datos, recrear la base desde el script de creación y borrar `backend/database/seeders/TestData/`, esta sección y los tres valores de `.env.example`.

---

## Pruebas

```bash
# Backend
cd backend
php artisan test

# Frontend
cd frontend
npm run build
```

> El frontend todavía no tiene ESLint configurado (`npm run lint` no existe como script); la única validación disponible hoy es que `npm run build` (type-check + build) termine sin errores.

---

## Convenciones

### Backend

- Flujo obligatorio: `Controller → FormRequest → Service → Model`; la respuesta se devuelve mediante un `Resource`.
- Los controladores no contienen lógica de negocio ni consultas Eloquent directas.
- Las transacciones se abren en los Services.
- Los modelos usan nombres en inglés y declaran la tabla explícitamente (`protected $table = 'grupo';`).
- Las Centrales de Riesgo Académica y de Admisión no se referencian entre sí.

Detalle completo (idioma, PSR-12, naming, capas) en [`.claude/rules/code-style-backend.md`](.claude/rules/code-style-backend.md).

### Frontend

| Elemento | Convención | Ejemplo |
|---|---|---|
| Componente | `PascalCase.tsx` | `StudentTable.tsx` |
| Página | `NombrePage.tsx` | `StudentsPage.tsx` |
| Hook | `useNombre.ts` | `useStudents.ts` |
| Servicio | `nombreService.ts` | `studentService.ts` |
| Tipos | `nombre.types.ts` | `student.types.ts` |

- `.tsx` solo para archivos con JSX; el resto, `.ts`.
- Un feature solo importa de otro feature a través de su `index.ts`.
- `components/ui/` se gestiona solo con el CLI de shadcn (`npm run shadcn -- add <componente>`); los componentes propios van en `components/common/`.
- Todas las peticiones HTTP pasan por `lib/api-client.ts`.

### General

- No subir archivos `.env` (ya excluidos vía `.gitignore`).
- `composer.lock` y `package-lock.json` se mantienen versionados.

---

## Flujo de trabajo

- `develop` es la rama de integración; `main` representa versiones estables. No se desarrolla directamente sobre ninguna de las dos.
- Cada Historia de Usuario se desarrolla en una rama propia creada desde `develop`, siguiendo el formato y los tipos definidos en [`.claude/rules/git-branches.md`](.claude/rules/git-branches.md) (`feature/HU-XXX-descripcion`, `fix/HU-XXX-descripcion`, `refactor/descripcion`, `release/vX.Y.Z`, `hotfix/vX.Y.Z-descripcion`).
- Los commits siguen el formato Conventional Commits definido en [`.claude/rules/git-commits.md`](.claude/rules/git-commits.md) (`feat:`, `fix:`, `refactor:`, `docs:`, `style:`, `test:`, `perf:`, `build:`, `ci:`, `chore:`).
- Los cambios se integran mediante Pull Request hacia `develop`, con revisión de al menos un integrante.

---

## Documentación

| Documento | Descripción |
|---|---|
| [`docs/architecture/overview.md`](docs/architecture/overview.md) | Descripción de la arquitectura y sus capas |
| `docs/architecture/justificacion-arquitectura.md` | Justificación de la arquitectura y de la estructura del repositorio *(pendiente de redactar)* |
| [`docs/architecture/stack.md`](docs/architecture/stack.md) | Stack tecnológico y versiones |
| [`deployment/docker/README.md`](deployment/docker/README.md) | Entorno de desarrollo con Docker (PHP, PostgreSQL y Redis) |
| [`docs/architecture/decisions/`](docs/architecture/decisions/) | Registro de decisiones de arquitectura (ADR) |
| [`.claude/rules/git-branches.md`](.claude/rules/git-branches.md) | Convención de nombres de ramas |
| [`.claude/rules/git-commits.md`](.claude/rules/git-commits.md) | Convención de mensajes de commit |
| [`.claude/rules/code-style-backend.md`](.claude/rules/code-style-backend.md) | Estándares de código del backend |

---

## Empresa

| | |
|---|---|
| **Razón social** | Corvus Code S.R.L. |
| **Representante legal** | Carlos Diego Mariscal Segovia |
| **Correo** | corvuscodesrl@gmail.com |
| **Consultora TIS** | Leticia Blanco Coca |
| **Convocatoria** | CPTIS-452026-2026 |

Proyecto académico desarrollado para la materia Taller de Ingeniería de Software — UMSS.
