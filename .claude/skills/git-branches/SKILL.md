---
name: git-branches
description: Convención de nombres de ramas Git de SCIEM (tipos feature/tech/fix/refactor/release/hotfix, identificadores HU/RNF, flujo GitFlow). Usar antes de crear, nombrar o fusionar una rama.
---

# Estándares para nombrar ramas en Git — Proyecto SCIEM

Para mantener un historial de ramas organizado, trazable hacia el Product Backlog y fácil de mantener entre varios integrantes, todo el equipo Corvus Code deberá seguir las siguientes convenciones al crear ramas en el repositorio. Esta convención complementa el estándar de commits ya definido y sigue el flujo de trabajo GitFlow tradicional (`main`, `develop`, `feature/*`, `release/*`, `hotfix/*`), con la adición de `tech/*`, `fix/*` y `refactor/*`.

## 1. Estructura del nombre de rama

```
<tipo>/<identificador>-<descripcion-corta>
```

Donde:

- **tipo**: indica la naturaleza del trabajo (ver sección 2).
- **identificador**: ID de Historia de Usuario (`HU-XX`), ID de requerimiento no funcional (`RNF-XX`) o número de versión (`vX.Y.Z`), según el tipo de rama (ver sección 3).
- **descripcion-corta**: resumen breve, en minúsculas y con guiones, de lo que hace la rama.

Ejemplo:

```
feature/HU-11-verificar-informacion-estudiante
```

## 2. Tipos de rama

| Tipo | Uso | Se crea desde | Se fusiona a |
|---|---|---|---|
| `feature/` | Historia de Usuario del Product Backlog: entrega valor directo al cliente o al usuario | `develop` | `develop` |
| `tech/` | Requerimiento no funcional del Backlog No Funcional: seguridad, rendimiento, despliegue, respaldos | `develop` | `develop` |
| `fix/` | Corrección de un error detectado durante el desarrollo (antes de producción) | `develop` | `develop` |
| `refactor/` | Reorganización o cambio de arquitectura sin alterar el comportamiento funcional | `develop` | `develop` |
| `release/` | Preparación y estabilización de una nueva versión | `develop` | `main` y `develop` |
| `hotfix/` | Corrección urgente de un error crítico ya en producción | `main` | `main` y `develop` |

**Diferencia clave `feature/` vs `tech/`:** `feature` desarrolla una Historia de Usuario, algo que el cliente o el usuario final pueden ver y valorar. `tech` desarrolla un requerimiento no funcional: es necesario e incluso exigido por el PETIS, pero no entrega valor visible por sí mismo — autenticación, protección de rutas, inmutabilidad de registros, respaldos, despliegue. Separar los dos tipos evita que al leer el listado de ramas alguien confunda una capa de seguridad con una entrega para el cliente.

**Diferencia clave `fix/` vs `hotfix/`:** `fix` corrige errores encontrados durante el desarrollo, antes de llegar a producción. `hotfix` corrige errores críticos que ya están en producción y requiere fusión inmediata a `main` y `develop`.

**Diferencia clave `tech/` vs `refactor/`:** `tech` implementa un requerimiento no funcional que **está en el backlog con su propio ID RNF** y que agrega o cambia comportamiento (el login agrega autenticación donde no había). `refactor` reorganiza código **sin cambiar el comportamiento** y no corresponde a ningún ítem del backlog. Si el trabajo tiene un ID RNF asignado, es `tech/`; si no lo tiene y nada cambia de cara al usuario ni a la API, es `refactor/`.

**Sobre `refactor/`:**
- Se usa para cambios de arquitectura o reorganización de código que no están ligados a una Historia de Usuario ni a un RNF (ej. reestructurar cómo se comunican los módulos, migrar un patrón de diseño, reorganizar carpetas del proyecto).
- Refactors pequeños y acotados a una funcionalidad puntual (limpiar un servicio, extraer una función) **no** necesitan rama propia: van como commits `refactor:` dentro de la misma rama `feature/`, `tech/` o `fix/` en la que ya se está trabajando.
- Al ser un cambio transversal, se recomienda avisar al equipo antes de abrirla y mantenerla abierta el menor tiempo posible, ya que puede generar conflictos con otras ramas activas en paralelo.

**Sobre `tech/`:**
- Se usa solo para un RNF con ID propio del Backlog No Funcional. Una tarea técnica que vive **dentro** de una Historia de Usuario (por ejemplo la red de control en tiempo real dentro de HU-10) va en la rama de esa HU, no en una `tech/` aparte.
- Varios RNF son transversales por naturaleza: tocan archivos de todas las features ya entregadas. Igual que con `refactor/`, conviene avisar al equipo antes de abrir la rama y mantenerla abierta el menor tiempo posible.

## 3. Identificador según el tipo de rama

| Tipo de rama | Identificador | Ejemplo |
|---|---|---|
| `feature/` | `HU-<id>` (obligatorio) | `feature/HU-11-verificar-informacion-estudiante` |
| `tech/` | `RNF-<id>` (obligatorio) | `tech/RNF-02-autenticacion` |
| `fix/` | `HU-<id>` o `RNF-<id>`, el del ítem que se corrige (obligatorio) | `fix/HU-05-modelo-nomina` |
| `refactor/` | Descripción corta, sin identificador | `refactor/reorganizar-modulo-autenticacion` |
| `release/` | Número de versión (SemVer) | `release/v1.3.0` |
| `hotfix/` | Versión + descripción corta | `hotfix/v1.2.1-login-docente` |

### Los dos rangos de identificadores

El Product Backlog maneja **dos secuencias independientes**:

| Secuencia | Rango | Significado |
|---|---|---|
| `HU-01` … `HU-35` | Requerimientos funcionales | Entregan valor al cliente o al usuario |
| `RNF-01` … `RNF-18` | Requerimientos no funcionales | Seguridad, rendimiento, despliegue, respaldos |

Los identificadores son correlativos y **no llevan información autocontenida**: `HU-11` no dice de qué módulo es ni qué hace, y eso es a propósito. El significado vive en el Product Backlog y en la tarjeta de Trello, no en el número. Si un ítem cambia de sprint o de alcance, el ID no cambia.

Ninguna rama `feature/`, `tech/` o `fix/` debe crearse sin que exista previamente la tarjeta de Trello correspondiente con su ID asignado.

### Nota sobre la numeración anterior

Hasta el Product Backlog v4 los identificadores eran de tres dígitos y seguían la numeración del backlog original (`HU-001` a `HU-056`), incluidos los requerimientos no funcionales, que también se numeraban como `HU-`. Las ramas, los commits y los Pull Requests creados antes del cambio conservan ese formato y **no se renombran**: renombrarlos rompería la trazabilidad del historial de Git.

Al leer el historial hay que tener presente que los dos formatos se cruzan y no significan lo mismo: `HU-012` del backlog original era «Registrar ambiente», que hoy es `HU-07`; y `HU-12` del backlog actual es «Iniciar examen», otra historia distinta. Cuando la diferencia importe, la descripción de la rama desambigua. Toda rama nueva usa el formato de dos dígitos y la secuencia que le corresponda.

## 4. Reglas para escribir el nombre de la rama

1. Todo en minúsculas, **excepto el identificador**, que conserva sus mayúsculas (`HU-11`, `RNF-02`).
2. Sin tildes, sin `ñ`, sin espacios ni caracteres especiales.
3. Usar guiones (`-`) para separar palabras dentro de la descripción; usar `/` únicamente para separar tipo e identificador.
4. Descripción corta y clara: máximo 4-5 palabras, indicando **qué** hace la rama, no **cómo** lo hace.
5. No usar `_` (guion bajo) ni `camelCase`.
6. No crear ramas personales genéricas (`juan-cambios`, `prueba`, `dev-ana`); toda rama debe estar asociada a uno de los seis tipos definidos.
7. Al finalizar el trabajo y fusionar la rama, esta debe eliminarse del repositorio remoto para mantener el listado de ramas limpio.

## 5. Ejemplos correctos

```
feature/HU-07-registrar-ambiente
feature/HU-08-habilitar-auxiliar
feature/HU-11-verificar-informacion-estudiante
tech/RNF-02-autenticacion
tech/RNF-03-proteccion-rutas-por-rol
tech/RNF-10-disponibilidad-ingreso
fix/HU-05-modelo-nomina
fix/RNF-02-manejo-401-frontend
refactor/reorganizar-modulo-autenticacion
refactor/migrar-arquitectura-servicios
release/v1.3.0
hotfix/v1.2.1-login-docente
```

## 6. Flujo de integración (GitFlow)

```
feature/HU-XX-descripcion    →   develop
tech/RNF-XX-descripcion      →   develop
fix/HU-XX-descripcion        →   develop
refactor/descripcion         →   develop
release/vX.Y.Z               →   main + develop
hotfix/vX.Y.Z-descripcion    →   main + develop
```

Toda rama `feature/`, `tech/`, `fix/` o `refactor/` se integra a `develop` mediante Pull Request, no mediante fusión directa. `release/*` y `hotfix/*` se fusionan tanto a `main` como a `develop` para mantener ambas ramas sincronizadas.

## 7. Objetivo

El objetivo de esta convención es mantener un historial de ramas ordenado y simple, permitir identificar de inmediato a qué ítem del backlog corresponde cada rama y si ese ítem entrega valor al cliente o es una capa técnica, facilitar la revisión de Pull Requests y evitar ramas huérfanas o sin trazabilidad dentro del repositorio de SCIEM.