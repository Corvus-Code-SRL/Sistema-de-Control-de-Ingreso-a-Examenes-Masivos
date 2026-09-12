# Sistema-de-Control-de-Ingreso-a-Examenes-Masivos
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

El directorio `backend/` contiene la API y la lógica de negocio desarrollada con Laravel.

Tecnologías principales:

- PHP 8.0.30
- Laravel Framework 8.83.29
- Eloquent ORM
- Composer 2.9.6

### Frontend

El directorio `frontend/` contiene la aplicación web.

Tecnologías principales:

- React 18.3.1
- React DOM 18.3.1
- TypeScript 5.7.3
- Vite 6.4.3
- Tailwind CSS 3.4.19

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

El directorio `deployment/` contiene la configuración necesaria para el despliegue del sistema.

```text
deployment/
└── docker/
    ├── backend/
    ├── frontend/
    ├── apache/
    └── redis/
```

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
- Tailwind CSS: 3.4.19
- PostCSS: 8.5.28
- Autoprefixer: 10.5.6

## Instalación

Clonar el repositorio:

```bash
git clone <URL_DEL_REPOSITORIO>
cd Sistema-de-Control-de-Ingreso-a-Examenes-Masivos
```

### Backend

Entrar al directorio:

```bash
cd backend
```

Instalar las dependencias:

```bash
composer install
```

Crear el archivo de variables de entorno:

```bash
cp .env.example .env
```

Generar la clave de Laravel:

```bash
php artisan key:generate
```

### Frontend

Desde la raíz del proyecto:

```bash
cd frontend
```

Instalar exactamente las dependencias definidas en `package-lock.json`:

```bash
npm ci
```

## Ejecución en desarrollo

### Backend

```bash
cd backend
php artisan serve
```

Por defecto estará disponible en:

```text
http://127.0.0.1:8000
```

### Frontend

```bash
cd frontend
npm run dev
```

Por defecto estará disponible en:

```text
http://localhost:5173
```

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

Redis se utiliza como infraestructura transversal para caché y otros mecanismos que se definan durante el desarrollo.

La documentación detallada se encuentra en:

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
chore/estructura-inicial
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

No utilizar `composer update`, `npm update` ni actualizar dependencias sin coordinación previa con el equipo.

Los archivos:

```text
composer.lock
package-lock.json
```

deben mantenerse dentro del repositorio para garantizar que todos los integrantes trabajen con las mismas versiones.

## Centrales de Riesgo

SCIEM mantiene dos Centrales de Riesgo independientes:

```text
Central de Riesgo Académica
→ estudiantes de la universidad

Central de Riesgo de Admisión
→ postulantes
```

Los registros de ambas centrales no deben mezclarse.
