# SCIEM — Sistema de Control de Ingreso a Exámenes Masivos

Plataforma de Corvus Code S.R.L. para la Universidad Mayor de San Simón: control de ingreso, validación e incidentes en exámenes masivos.

**IMPORTANTE**: responde siempre en español, sin importar el idioma de la consulta. Esto aplica solo a tus respuestas y explicaciones — el código sigue las reglas de idioma de `.claude/rules/code-style-backend.md`.

## Estructura (monorepo)

- `backend/` — API Laravel (PHP)
- `frontend/` — SPA React + TypeScript (Vite)
- `docs/` — arquitectura, API, BD, QA, historias de usuario
- `deployment/` — Docker (no necesario para dev local)

**Estado actual**: ambas apps son scaffolds. El backend ya tiene la estructura de carpetas por módulo (`Http/Controllers/<Modulo>`, `Http/Requests/<Modulo>`, `Services/<Modulo>`, `routes/api/<modulo>.php`) pero sin lógica ni rutas reales todavía; el frontend tiene primitivas shadcn listas y `features/*` vacíos.

## Comandos

**Backend** (`cd backend`): `composer install` (nunca `update` sin coordinar) · `php artisan migrate` (nunca `migrate:fresh` con datos que importen) · `php artisan serve` → `127.0.0.1:8000` · `php artisan test`

**Frontend** (`cd frontend`): `npm ci` (nunca `update` sin coordinar) · `npm run dev` → `localhost:5173` · `npm run build` (validar antes de cualquier PR) · `npm run shadcn -- add <componente>`

## Arquitectura

Monolito modular desacoplado (MVC + capa de Services):

React + TS → REST API → Controller → Form Request → Service → Eloquent Model → PostgreSQL (respuesta vía Resource)


- Controllers delgados: reciben el Request, delegan a Services (`app/Services/<Modulo>/`) y devuelven un `Resource`. Nunca lógica de negocio ni queries Eloquent en Controller o Modelo.
- Transacciones (`DB::transaction`) se abren en los Services, no en Controllers.
- Un Service puede inyectar Services de otro módulo; Controllers y Requests nunca se llaman entre módulos.
- `app/Services/` es solo lógica de negocio. Lo transversal (cache, storage, formato de respuesta) va en `app/Support/` (`Support/Cache`, `Support/Storage`, `Support/Http`) — `Support/` nunca depende de `Services/`.
- Redis es infraestructura transversal (cache, sesiones, colas).
- Modelos: nombre de clase en inglés, `protected $table` explícito (tabla en español, ej. `'grupo'`).
- Rutas por módulo en `routes/api/<modulo>.php`, cargadas desde `routes/api.php`.
- Frontend: `components/ui` (primitivas shadcn — revisar antes de crear una nueva), `components/layout`, `components/common`, `features/<nombre>/` (cada feature con components/pages/services/hooks/types propios, expuestos solo vía su `index.ts` — un feature nunca importa de rutas internas de otro). Alias `@/*` → `src/*`. Naming: `PascalCase.tsx` (componentes), `useNombre.ts` (hooks), `nombreService.ts` (servicios), `nombre.types.ts` (tipos). Toda llamada HTTP pasa por `lib/api-client.ts`.

### Módulos

| Módulo backend | Feature(s) frontend |
|---|---|
| `Security` | `auth`, `users`, `audit-log` |
| `Academic` | `subjects`, `groups`, `students` |
| `Exams` | `exams`, `classrooms`, `assistants` |
| `EntryControl` | `entry-control` |
| `Incidents` | `incidents` |
| `RiskCenter/Academic` | `risk-center/academic` |
| `RiskCenter/Admission` | `risk-center/admission` |
| `Admissions` | `admissions` |

### Central de Riesgo
Dos contextos independientes que **nunca** deben mezclarse ni referenciarse entre sí (ni en Services ni en features): `RiskCenter/Academic` (estudiantes) y `RiskCenter/Admission` (postulantes).

## Estándares del equipo

Detalle completo en `.claude/rules/`:
- `git-branches.md` — nomenclatura de ramas
- `git-commits.md` — formato de commits
- `code-style-backend.md` — PSR-12, idioma, nombres, capas, comentarios

## Reglas rápidas
- `composer.lock` / `package-lock.json` siempre committeados.
- Ramas parten de `develop`, nunca de `main`.
- No commitear `.env`.
- Nunca ejecutes `git push` ni crees pull requests, bajo ninguna circunstancia, sin mi autorización.
- Puedes hacer commits locales cuando te lo pida explícitamente.