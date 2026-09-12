# Arquitectura de SCIEM

## 1. Descripción general

SCIEM utiliza una arquitectura cliente-servidor con separación entre frontend y backend.

El flujo principal del sistema es:

React + TypeScript → API REST → Laravel → Services → Eloquent → PostgreSQL

Redis funciona como infraestructura transversal para caché y otros mecanismos que se requieran.

## 2. Frontend

El frontend utiliza:

- React
- TypeScript
- Vite
- Tailwind CSS

La estructura principal se encuentra en:

`frontend/src/`

### components/

Contiene componentes reutilizables en distintas partes del sistema.

- `ui/`: botones, inputs, tablas, modales, cards, badges, etc.
- `layout/`: sidebar, header y estructuras generales de pantalla.
- `common/`: componentes reutilizables de propósito general.

### features/

Agrupa el código según las funcionalidades del sistema.

Principales features:

- `auth/`
- `dashboard/`
- `subjects/`
- `groups/`
- `students/`
- `exams/`
- `admissions/`
- `incidents/`
- `assistants/`
- `risk-center/academic/`
- `risk-center/admission/`

Cada feature puede contener, cuando sea necesario:

- `components/`
- `pages/`
- `services/`
- `hooks/`
- `types/`

### services/

Contiene servicios globales del frontend, principalmente comunicación con la API.

### hooks/

Contiene hooks reutilizables de React.

### context/

Contiene estados globales administrados mediante React Context.

### routes/

Contiene las rutas del frontend y mecanismos de protección de rutas.

### types/

Contiene tipos e interfaces TypeScript compartidos.

### utils/

Contiene funciones auxiliares reutilizables.

### config/

Contiene configuraciones generales del frontend.

## 3. Backend

El backend utiliza Laravel bajo el patrón MVC, complementado con una capa de servicios.

El flujo principal es:

Route → Controller → Request → Service → Eloquent → PostgreSQL

### Controllers/

Reciben solicitudes HTTP y delegan la lógica de negocio a los Services.

### Requests/

Validan los datos recibidos antes de que lleguen a los Controllers.

### Resources/

Definen y transforman las respuestas enviadas por la API.

### Models/

Contienen los modelos Eloquent y las relaciones con la base de datos.

### Services/

Contienen la lógica de negocio del sistema.

Principales módulos:

- `Auth/`
- `Academic/`
- `Exams/`
- `Admissions/`
- `Incidents/`
- `RiskCenter/Academic/`
- `RiskCenter/Admission/`
- `Cache/`
- `Storage/`

### Policies/

Gestionan permisos y autorización.

### Events/ y Listeners/

Permiten ejecutar acciones desacopladas ante eventos del sistema cuando sea necesario.

### Jobs/

Contiene tareas que puedan ser ejecutadas mediante colas.

### Notifications/

Contiene las notificaciones generadas por el sistema.

## 4. Central de Riesgo

SCIEM mantiene dos Centrales de Riesgo independientes.

### Central de Riesgo Académica

Contiene registros asociados a estudiantes de la universidad y a exámenes académicos.

### Central de Riesgo de Admisión

Contiene registros asociados a postulantes de procesos de ingreso a la universidad, incluyendo exámenes de admisión, propedéuticos y otras modalidades.

Los registros de ambas centrales no deben mezclarse.

## 5. Redis

Redis se considera infraestructura transversal y no pertenece a un módulo funcional específico.

Puede utilizarse para:

- caché;
- sesiones;
- colas;
- optimización de consultas;
- otros mecanismos que se definan durante el desarrollo.

La lógica propia relacionada con caché se organiza en:

`backend/app/Services/Cache/`

## 6. Base de datos

PostgreSQL es el sistema gestor de base de datos.

Laravel utiliza Eloquent ORM para interactuar con PostgreSQL.

Las migraciones se encuentran en:

`backend/database/migrations/`

## 7. Despliegue

La configuración de despliegue se encuentra en:

`deployment/docker/`

Incluye:

- `backend/`: configuración Docker del backend.
- `frontend/`: configuración Docker del frontend.
- `apache/`: configuración del servidor Apache.
- `redis/`: configuración de Redis.

Docker se utilizará principalmente para reproducir el entorno de despliegue.

## 8. Estándar TypeScript

Usar `.tsx` cuando el archivo contenga JSX.

Ejemplos:

- `Button.tsx`
- `StudentTable.tsx`
- `DashboardPage.tsx`
- `App.tsx`

Usar `.ts` cuando el archivo no contenga JSX.

Ejemplos:

- `studentService.ts`
- `validators.ts`
- `permissions.ts`
- `student.types.ts`

### Convenciones de nombres

Componentes React:

`PascalCase.tsx`

Ejemplo:

`StudentTable.tsx`

Páginas:

`NombrePage.tsx`

Ejemplo:

`StudentsPage.tsx`

Hooks:

`useNombre.ts`

Ejemplo:

`useStudents.ts`

Servicios:

`nombreService.ts`

Ejemplo:

`studentService.ts`

Tipos específicos:

`nombre.types.ts`

Ejemplo:

`student.types.ts`
