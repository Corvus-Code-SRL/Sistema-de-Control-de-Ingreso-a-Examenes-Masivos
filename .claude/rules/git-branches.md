# Estándares para nombrar ramas en Git — Proyecto SCIEM

Para mantener un historial de ramas organizado, trazable hacia el Product Backlog y fácil de mantener entre varios integrantes, todo el equipo Corvus Code deberá seguir las siguientes convenciones al crear ramas en el repositorio. Esta convención complementa el estándar de commits ya definido y sigue el flujo de trabajo GitFlow tradicional (`main`, `develop`, `feature/*`, `release/*`, `hotfix/*`), con la adición de `fix/*` y `refactor/*`.

## 1. Estructura del nombre de rama

```
<tipo>/<identificador>-<descripcion-corta>
```

Donde:

- **tipo**: indica la naturaleza del trabajo (ver sección 2).
- **identificador**: ID de Historia de Usuario (`HU-XXX`) o número de versión (`vX.Y.Z`), según el tipo de rama (ver sección 3).
- **descripcion-corta**: resumen breve, en minúsculas y con guiones, de lo que hace la rama.

Ejemplo:

```
feature/HU-023-validacion-codigo-sis
```

## 2. Tipos de rama

| Tipo | Uso | Se crea desde | Se fusiona a |
|---|---|---|---|
| `feature/` | Nueva funcionalidad correspondiente a una Historia de Usuario | `develop` | `develop` |
| `fix/` | Corrección de un error detectado durante el desarrollo (antes de producción) | `develop` | `develop` |
| `refactor/` | Reorganización o cambio de arquitectura sin alterar el comportamiento funcional | `develop` | `develop` |
| `release/` | Preparación y estabilización de una nueva versión | `develop` | `main` y `develop` |
| `hotfix/` | Corrección urgente de un error crítico ya en producción | `main` | `main` y `develop` |

**Diferencia clave `fix/` vs `hotfix/`:** `fix` corrige errores encontrados durante el desarrollo, antes de llegar a producción. `hotfix` corrige errores críticos que ya están en producción y requiere fusión inmediata a `main` y `develop`.

**Sobre `refactor/`:**
- Se usa para cambios de arquitectura o reorganización de código que no están ligados a una sola Historia de Usuario (ej. reestructurar cómo se comunican los módulos, migrar un patrón de diseño, reorganizar carpetas del proyecto).
- Refactors pequeños y acotados a una funcionalidad puntual (limpiar un servicio, extraer una función) **no** necesitan rama propia: van como commits `refactor:` dentro de la misma rama `feature/` o `fix/` en la que ya se está trabajando.
- Al ser un cambio transversal, se recomienda avisar al equipo antes de abrirla y mantenerla abierta el menor tiempo posible, ya que puede generar conflictos con otras ramas `feature/` activas en paralelo.

## 3. Identificador según el tipo de rama

| Tipo de rama | Identificador | Ejemplo |
|---|---|---|
| `feature/` | `HU-<id>` (obligatorio) | `feature/HU-023-validacion-codigo-sis` |
| `fix/` | `HU-<id>` (obligatorio) | `fix/HU-031-error-carga-csv` |
| `refactor/` | Descripción corta (HU-id solo si existe una historia técnica registrada) | `refactor/reorganizar-modulo-autenticacion` |
| `release/` | Número de versión (SemVer) | `release/v1.3.0` |
| `hotfix/` | Versión + descripción corta | `hotfix/v1.2.1-fix-login-docente` |

Cada Historia de Usuario del backlog debe tener asignado un correlativo único desde su creación en Trello (`HU-001`, `HU-002`, etc.). Ninguna rama `feature/` o `fix/` debe crearse sin que exista previamente la tarjeta correspondiente con su ID asignado.

## 4. Reglas para escribir el nombre de la rama

1. Todo en minúsculas.
2. Sin tildes, sin `ñ`, sin espacios ni caracteres especiales.
3. Usar guiones (`-`) para separar palabras dentro de la descripción; usar `/` únicamente para separar tipo e identificador.
4. Descripción corta y clara: máximo 4-5 palabras, indicando **qué** hace la rama, no **cómo** lo hace.
5. No usar `_` (guion bajo) ni `camelCase`.
6. No crear ramas personales genéricas (`juan-cambios`, `prueba`, `dev-ana`); toda rama debe estar asociada a uno de los cinco tipos definidos.
7. Al finalizar el trabajo y fusionar la rama, esta debe eliminarse del repositorio remoto para mantener el listado de ramas limpio.

## 5. Ejemplos correctos

```
feature/HU-012-registro-estudiantes-excepcionales
feature/HU-023-validacion-codigo-sis
fix/HU-031-error-carga-csv
fix/HU-045-duplicado-registro-ingreso
refactor/reorganizar-modulo-autenticacion
refactor/migrar-arquitectura-servicios
release/v1.3.0
hotfix/v1.2.1-fix-login-docente
```

## 6. Flujo de integración (GitFlow)

```
feature/HU-XXX-descripcion   →   develop
fix/HU-XXX-descripcion       →   develop
refactor/descripcion         →   develop
release/vX.Y.Z               →   main + develop
hotfix/vX.Y.Z-descripcion    →   main + develop
```

Toda rama `feature/`, `fix/` o `refactor/` se integra a `develop` mediante Pull Request, no mediante fusión directa. `release/*` y `hotfix/*` se fusionan tanto a `main` como a `develop` para mantener ambas ramas sincronizadas.

## 7. Objetivo

El objetivo de esta convención es mantener un historial de ramas ordenado y simple, permitir identificar de inmediato a qué Historia de Usuario del Product Backlog corresponde cada rama, facilitar la revisión de Pull Requests y evitar ramas huérfanas o sin trazabilidad dentro del repositorio de SCIEM.