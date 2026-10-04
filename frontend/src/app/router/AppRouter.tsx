import type { ReactElement } from 'react'
import { Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { LoadingState } from '@/components/common/LoadingState'
import { MyAssignmentsPage, MyAssistantsPage } from '@/features/assistants'
import {
  LOGIN_REDIRECT_KEY,
  LoginPage,
  ProtectedRoute,
  SessionExpiredOverlay,
  resolveDestination,
  useAuth,
  useCurrentUser,
  type Capability,
} from '@/features/auth'
import { CreateExamPage, ExamDetailPage, ExamsPage } from '@/features/exams'
import { CourseDetailPage, MyCoursesPage, SubjectGroupsPage } from '@/features/groups'
import { ClassroomsPage } from '@/features/classrooms'
import { AdminSubjectsPage, EditarMateriaPage, SubjectsPage } from '@/features/subjects'
import { CuentaDetallePage, CuentasPage } from '@/features/users'

/** Envuelve una pantalla con la capacidad que exige. Sin sesión deja pasar (ver ProtectedRoute). */
function guarded(capability: Capability, element: ReactElement) {
  return <ProtectedRoute capability={capability}>{element}</ProtectedRoute>
}

/**
 * Rutas de la gestión académica del docente.
 *
 * El contexto de trabajo viaja en la URL como par carrera-materia, nunca como
 * materia sola: es lo que permite compartir o recargar una vista sin perderlo.
 */
function DocenteRoutes() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/materias" replace />} />
      <Route path="/materias" element={guarded('grupos.gestionar', <SubjectsPage />)} />
      <Route
        path="/carreras/:idCarrera/materias/:idMateria/grupos"
        element={guarded('grupos.gestionar', <SubjectGroupsPage />)}
      />
      <Route path="/mis-cursos" element={guarded('grupos.gestionar', <MyCoursesPage />)} />
      <Route
        path="/mis-auxiliares"
        element={guarded('auxiliares.gestionar', <MyAssistantsPage />)}
      />
      <Route path="/cursos/:idGrupo" element={guarded('grupos.gestionar', <CourseDetailPage />)} />
      <Route path="/examenes/nuevo" element={guarded('examenes.gestionar', <CreateExamPage />)} />
      <Route
        path="/examenes/programados"
        element={guarded('examenes.gestionar', <ExamsPage />)}
      />
      <Route path="/examenes/:examId" element={guarded('examenes.gestionar', <ExamDetailPage />)} />
      <Route path="*" element={<Navigate to="/materias" replace />} />
    </Routes>
  )
}

/**
 * Rutas del auxiliar (HU-09): por ahora solo la consulta de sus exámenes y del
 * ambiente que el docente le asignó.
 */
function AuxiliarRoutes() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/mis-examenes" replace />} />
      <Route path="/mis-examenes" element={guarded('ingreso.operar', <MyAssignmentsPage />)} />
      <Route path="*" element={<Navigate to="/mis-examenes" replace />} />
    </Routes>
  )
}

/**
 * Rutas de la administración del catálogo institucional.
 *
 * Cuentas y Materias tienen pantallas disponibles. Facultades y carreras
 * permanecen pendientes de sus respectivas historias de usuario.
 */
function AdministradorRoutes() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/cuentas" replace />} />
      <Route path="/cuentas" element={guarded('administracion.gestionar', <CuentasPage />)} />
      <Route
        path="/cuentas/:idUsuario"
        element={guarded('administracion.gestionar', <CuentaDetallePage />)}
      />
      <Route path="/ambientes" element={guarded('administracion.gestionar', <ClassroomsPage />)} />
      <Route
        path="/materias"
        element={guarded('administracion.gestionar', <AdminSubjectsPage />)}
      />
      <Route
        path="/materias/:idMateria/editar"
        element={guarded('administracion.gestionar', <EditarMateriaPage />)}
      />

      <Route path="*" element={<Navigate to="/cuentas" replace />} />
    </Routes>
  )
}

/** Quien ya tiene sesión no necesita el formulario: vuelve a donde iba o a la entrada de su rol. */
function LoginRoute() {
  const { estado, rol } = useAuth()
  const location = useLocation()

  if (estado === 'autenticado') {
    const state = location.state as Record<string, unknown> | null

    return (
      <Navigate to={resolveDestination(state?.[LOGIN_REDIRECT_KEY], rol?.nombre_rol)} replace />
    )
  }

  return <LoginPage />
}

/**
 * Cada área tiene su propio juego de rutas, incluido su destino por defecto.
 *
 * El área sale del rol de la sesión (Administrador → administrador; Auxiliar → auxiliar; Docente →
 * docente; sin sesión → docente). Separarlas evita que una URL de un área caiga en la pantalla
 * de la otra.
 */
function AreaRoutes() {
  const { area } = useCurrentUser()

  if (area === 'administrador') return <AdministradorRoutes />
  if (area === 'auxiliar') return <AuxiliarRoutes />

  return <DocenteRoutes />
}

export function AppRouter() {
  const { estado } = useAuth()

  // Con un token guardado no se pinta nada hasta saber si sigue vivo: así la recarga de una
  // sesión válida no muestra por un instante la pantalla equivocada.
  if (estado === 'verificando') {
    return <LoadingState rows={3} label="Verificando la sesión" />
  }

  const blocked = estado === 'expirada'

  return (
    <>
      {/* `inert` saca la pantalla de la tabulación y de los lectores mientras el aviso la bloquea. */}
      <div {...(blocked ? { inert: '' } : {})}>
        <Routes>
          <Route path="/login" element={<LoginRoute />} />
          <Route path="/*" element={<AreaRoutes />} />
        </Routes>
      </div>

      <SessionExpiredOverlay />
    </>
  )
}
