# Sistema de Control de Ingreso a Exámenes Masivos

Plataforma web para el control de ingreso, validación de estudiantes y gestión de incidentes en exámenes universitarios.

# SCIEM

Proyecto desarrollado por **Corvus Code** para la materia TIS de la Universidad Mayor de San Simón.

## Estructura del proyecto

```text
SCIEM/
├── backend/
├── frontend/
├── docs/
├── deployment/
├── .gitignore
├── mise.toml
└── README.md
```

### Backend

El directorio `backend/` contiene la API, las reglas de negocio y el acceso a datos desarrollados con Laravel.

Tecnologías principales:

- PHP 8.0.30
- Laravel Framework 8.83.29
- Eloquent ORM
- Composer 2.9.6
- PostgreSQL 15.0
- Redis 7.4.3

### Frontend

El directorio `frontend/` contiene la aplicación web desarrollada con React y TypeScript.

Tecnologías principales:

- Node.js 22.23.2
- npm 10.9.8
- React 18.3.1
- React DOM 18.3.1
- TypeScript 5.7.3
- Vite 6.4.3
- Tailwind CSS 4.3.3
- @tailwindcss/vite 4.3.3
- shadcn CLI 4.21.0
- Radix UI 1.6.7
- Lucide React 1.45.0
- tw-animate-css 1.4.0

### Documentación

El directorio `docs/` contiene la documentación técnica y funcional del sistema.

```text
docs/
├── architecture/
├── database/
├── api/
├── qa/
├── user-stories/
└── diagrams/
```

### Deployment

El directorio `deployment/` contiene la configuración relacionada con el despliegue del sistema.

```text
deployment/
└── docker/
    ├── backend/
    ├── frontend/
    ├── apache/
    └── redis/
```

> Docker no es requerido para el desarrollo local. Los integrantes trabajan con las dependencias instaladas directamente en su entorno de desarrollo.

## Stack tecnológico

### Backend

- PHP: 8.0.30
- Laravel Framework: 8.83.29
- Composer: 2.9.6
- Apache HTTP Server: 2.4.62
- PostgreSQL: 15.0
- Redis: 7.4.3
- Eloquent ORM: incluido con Laravel

### Frontend

- Node.js: 22.23.2
- npm: 10.9.8
- React: 18.3.1
- React DOM: 18.3.1
- TypeScript: 5.7.3
- Vite: 6.4.3
- Tailwind CSS: 4.3.3
- @tailwindcss/vite: 4.3.3
- shadcn CLI: 4.21.0
- Radix UI: 1.6.7
- Lucide React: 1.45.0
- tw-animate-css: 1.4.0

## Requisitos para desarrollo

Antes de clonar el proyecto se recomienda disponer de:

```text
Git
Node.js 22.23.2
npm 10.9.8
PHP 8.0.30
Composer 2.9.6
PostgreSQL 15
```

El proyecto utiliza `mise` para fijar la versión de Node.js mediante el archivo:

```text
mise.toml
```

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/Corvus-Code-SRL/Sistema-de-Control-de-Ingreso-a-Examenes-Masivos.git
cd Sistema-de-Control-de-Ingreso-a-Examenes-Masivos
```

### 2. Cambiar a la rama de desarrollo

```bash
git checkout develop
git pull origin develop
```

La rama `develop` constituye la base para el desarrollo de nuevas funcionalidades.

### 3. Configurar Node.js

Si se utiliza `mise`:

```bash
mise install
```

Comprobar las versiones:

```bash
node -v
npm -v
```

Versiones esperadas:

```text
Node.js 22.23.2
npm 10.9.8
```

## Configuración del Backend

Entrar al directorio:

```bash
cd backend
```

Instalar exactamente las dependencias definidas en `composer.lock`:

```bash
composer install
```

Crear el archivo local de variables de entorno:

```bash
cp .env.example .env
```

En Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Generar la clave de Laravel:

```bash
php artisan key:generate
```

### Base de datos

SCIEM utiliza PostgreSQL.

Cada desarrollador debe crear una base de datos local para el proyecto y configurar sus credenciales en:

```text
backend/.env
```

Ejemplo:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sciem
DB_USERNAME=postgres
DB_PASSWORD=
```

Las credenciales son locales y no deben subirse al repositorio.

Cuando existan migraciones pendientes:

```bash
php artisan migrate
```

> No utilizar `php artisan migrate:fresh` sobre una base de datos con información que se desee conservar, ya que elimina las tablas antes de volver a ejecutar las migraciones.

## Configuración del Frontend

Desde la raíz del proyecto:

```bash
cd frontend
```

Instalar exactamente las dependencias definidas en `package-lock.json`:

```bash
npm ci
```

Validar que el frontend compile correctamente:

```bash
npm run build
```

### shadcn/ui

shadcn ya se encuentra configurado dentro del proyecto.

No es necesario instalarlo globalmente.

Antes de agregar un componente nuevo se debe comprobar si ya existe en:

```text
frontend/src/components/ui/
```

Para agregar un componente nuevo se debe utilizar la versión definida por el proyecto:

```bash
npm run shadcn -- add <componente>
```

Ejemplo:

```bash
npm run shadcn -- add checkbox
```

No utilizar versiones diferentes del CLI sin coordinación previa con el equipo.

## Ejecución en desarrollo

Para trabajar con el sistema se recomienda utilizar dos terminales.

### Backend

Desde la raíz del proyecto:

```bash
cd backend
php artisan serve
```

Por defecto estará disponible en:

```text
http://127.0.0.1:8000
```

### Frontend

En otra terminal:

```bash
cd frontend
npm run dev
```

Por defecto estará disponible en:

```text
http://localhost:5173
```

Apache no es necesario para ejecutar el proyecto durante el desarrollo local. Su configuración se considera principalmente para el entorno de despliegue.

## Validación del entorno

Antes de comenzar a desarrollar, cada integrante debe comprobar que los siguientes comandos funcionan correctamente.

### Backend

```bash
cd backend
composer install
php artisan --version
```

La versión esperada de Laravel es:

```text
Laravel Framework 8.83.29
```

### Frontend

```bash
cd frontend
npm ci
npm run build
```

El build debe finalizar sin errores.

## Arquitectura

SCIEM utiliza una arquitectura cliente-servidor.

El flujo general es:

```text
React + TypeScript
        ↓
      API REST
        ↓
      Laravel
        ↓
     Services
        ↓
     Eloquent
        ↓
    PostgreSQL
```

En el backend se utiliza principalmente el siguiente flujo:

```text
Route
  ↓
Controller
  ↓
Form Request
  ↓
Service
  ↓
Eloquent Model
  ↓
PostgreSQL
```

Redis se considera infraestructura transversal para caché y otros mecanismos que se definan durante el desarrollo.

La documentación detallada de arquitectura se encuentra en:

```text
docs/architecture/overview.md
```

El stack tecnológico se encuentra documentado en:

```text
docs/architecture/stack.md
```

## Convención de ramas

Las ramas principales son:

```text
main
develop
```

`main` representa las versiones estables del sistema.

`develop` constituye la rama base para el desarrollo.

No se debe desarrollar directamente sobre `main` ni `develop`.

Las ramas de trabajo siguen las siguientes convenciones:

```text
feature/<descripcion>
fix/<descripcion>
hotfix/<descripcion>
chore/<descripcion>
release/<version>
```

Ejemplos:

```text
feature/registro-estudiantes
feature/configuracion-examen
fix/validacion-codigo-sis
chore/configurar-shadcn
release/v1.0.0
```

Las descripciones deben escribirse:

- en español;
- en minúsculas;
- utilizando guiones;
- sin espacios;
- sin tildes;
- sin `ñ`;
- sin caracteres especiales.

### Crear una rama de trabajo

Antes de iniciar una tarea:

```bash
git checkout develop
git pull origin develop
```

Crear la nueva rama:

```bash
git checkout -b feature/<descripcion>
```

Ejemplo:

```bash
git checkout -b feature/registro-estudiantes
```

Al terminar el trabajo se debe realizar un Pull Request hacia:

```text
develop
```

No se deben realizar Pull Requests de una rama `feature/*` directamente hacia `main`.

## Convención de commits

Se recomienda utilizar mensajes breves y descriptivos.

Ejemplos:

```text
feat: implementar registro de estudiantes
fix: corregir validacion de codigo sis
chore: agregar componente checkbox
docs: actualizar configuracion de desarrollo
```

## Gestión de dependencias

Después de clonar el repositorio se debe utilizar:

### Backend

```bash
composer install
```

### Frontend

```bash
npm ci
```

No utilizar:

```text
composer update
npm update
```

ni actualizar dependencias sin coordinación previa con el equipo.

Los archivos:

```text
composer.lock
package-lock.json
```

deben mantenerse dentro del repositorio para garantizar que todos los integrantes trabajen con las mismas versiones.

El proyecto utiliza versiones exactas de sus dependencias para reducir diferencias entre entornos de desarrollo.

## Estándares del Frontend

Los componentes reutilizables de interfaz se encuentran principalmente en:

```text
frontend/src/components/ui/
```

Los componentes globales y reutilizables que no pertenezcan estrictamente al conjunto base de UI pueden ubicarse en:

```text
frontend/src/components/common/
```

Los componentes de estructura general de la aplicación se encuentran en:

```text
frontend/src/components/layout/
```

Los componentes específicos de una funcionalidad deben mantenerse dentro de su correspondiente `feature`.

Ejemplos:

```text
frontend/src/features/students/
frontend/src/features/exams/
frontend/src/features/groups/
frontend/src/features/admissions/
```

Cuando exista un componente base en shadcn o en `components/ui`, debe reutilizarse y adaptarse antes de crear un componente equivalente desde cero.

No se deben crear colores, radios, espaciados o estilos arbitrarios cuando ya exista un token o una regla definida por el Design System de SCIEM.

La interfaz utiliza Lucide como sistema principal de iconografía.

## Centrales de Riesgo

SCIEM mantiene dos contextos independientes para la gestión de riesgo:

```text
Central de Riesgo Académica
→ estudiantes de la universidad

Central de Riesgo de Admisión
→ postulantes
```

Los registros de ambas centrales no deben mezclarse.

## Recomendaciones de trabajo en equipo

Antes de comenzar a trabajar en una Historia de Usuario:

```bash
git checkout develop
git pull origin develop
git checkout -b feature/<descripcion>
```

Durante el desarrollo:

- reutilizar componentes existentes antes de crear nuevos;
- respetar la arquitectura definida;
- no modificar dependencias sin coordinación;
- no subir archivos `.env`;
- no desarrollar directamente sobre `main` o `develop`;
- mantener los cambios relacionados con una tarea dentro de su rama correspondiente;
- ejecutar las validaciones del frontend y backend antes de realizar un Pull Request.

Antes de enviar un Pull Request se recomienda comprobar:

```bash
cd frontend
npm run build
```

y, para el backend:

```bash
cd backend
php artisan --version
```

El Pull Request debe realizarse hacia `develop`.
