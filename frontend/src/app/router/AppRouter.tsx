import type { ReactElement } from 'react'
import { Navigate, Route, Routes, useLocation, useParams, useSearchParams } from 'react-router-dom'
import { LoadingState } from '@/components/common/LoadingState'
import { MyAssignmentsPage, MyAssistantsPage } from '@/features/assistants'
import { ExamAssistantsPage } from '@/features/assistants/pages/ExamAssistantsPage'
import {
  CHANGE_PASSWORD_PATH,
  CambiarContrasenaPage,
  LOGIN_REDIRECT_KEY,
  LoginPage,
  ProtectedRoute,
  resolveDestination,
  useAuth,
  useCurrentUser,
  type Capability,
} from '@/features/auth'
import {
  EntryControlPage,
  LATE_ENTRY_EXAMS_PATH,
  LATE_ENTRY_PATH,
  LateEntryExamsPage,
  LateEntryPage,
  OpenEntryControlsPage,
} from '@/features/entry-control'
import {
  CreateExamPage,
  EditExamPage,
  ExamDetailPage,
  ExamInProgressPage,
  ExamsPage,
  FinalReportPage,
  InProgressExamsPage,
  type ExamInProgressExtensions,
} from '@/features/exams'
import {
  GroupAssistantsPage,
  GroupDetailPage,
  GroupEditPage,
  GroupExamsPage,
  MyCoursesPage,
  SubjectGroupsPage,
} from '@/features/groups'
import { ClassroomsPage } from '@/features/classrooms'
import { RiskCenterPage } from '@/features/risk-center/academic'
import { RosterUploadPage } from '@/features/students'
import {
  AdminSubjectAssignmentsPage,
  AdminSubjectCatalogPage,
  SubjectsPage,
} from '@/features/subjects'
import { CuentaDetallePage, ModificarRolPage, RegistrarCuentaPage } from '@/features/users'
import { IncidentsPage } from '@/features/incidents'
import { AlienAttemptsReportPage } from '@/features/reports/academic'

// Punto de composición de HU-037, HU-038-B, HU-042 y HU-057.
// Cada feature puede aportar componentes aquí sin editar ExamInProgressPage.
const examInProgressExtensions: ExamInProgressExtensions = {}

/** Envuelve una pantalla con la capacidad que exige. Sin sesión redirige al login (ver ProtectedRoute). */
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
      <Route
        path="/grupos/:idGrupo"
        element={guarded('grupos.gestionar', <GroupDetailPage />)}
      />

      <Route
        path="/grupos/:idGrupo/examenes"
        element={guarded('examenes.gestionar', <GroupExamsPage />)}
      />
      <Route
        path="/grupos/:idGrupo/auxiliares"
        element={guarded('auxiliares.gestionar', <GroupAssistantsPage />)}
      />
      <Route
        path="/grupos/:idGrupo/editar"
        element={guarded('grupos.gestionar', <GroupEditPage />)}
      />

      <Route
        path="/grupos/:idGrupo/nomina"
        element={guarded('grupos.gestionar', <RosterUploadPage />)}
      />

      <Route
        path="/cursos/:idGrupo"
        element={<CourseDetailRedirect />}
      />

      <Route
        path="/examenes/nuevo"
        element={guarded('examenes.gestionar', <CreateExamPage />)}
      />

      <Route
        path="/examenes/programados"
        element={guarded('examenes.gestionar', <ExamsPage />)}
      />

      <Route
        path="/examenes/:examId/auxiliares"
        element={guarded('auxiliares.examen', <ExamAssistantsPage />)}
      />

      <Route
        path="/examenes/:examId/editar"
        element={guarded('examenes.gestionar', <EditExamPage />)}
      />

      <Route
        path="/examenes/:examId"
        element={guarded('examenes.gestionar', <ExamDetailPage />)}
      />

      <Route
        path="/examenes/:examId/reporte-final"
        element={guarded('examenes.gestionar', <FinalReportPage />)}
      />

      <Route path="/control-ingreso" element={guarded('ingreso.operar', <OpenEntryControlsPage />)} />

      <Route
        path="/examenes/:examId/control-ingreso"
        element={guarded('ingreso.operar', <EntryControlPage />)}
      />

      <Route
        path="/examenes/en-curso"
        element={guarded('ingreso.operar', <InProgressExamsPage />)}
      />

      <Route
        path="/examenes/:examId/en-curso"
        element={guarded('ingreso.operar', <ExamInProgressPage extensions={examInProgressExtensions} />)}
      />
      <Route path={LATE_ENTRY_EXAMS_PATH} element={guarded('ingreso.operar', <LateEntryExamsPage />)} />
      <Route path={LATE_ENTRY_PATH} element={guarded('ingreso.operar', <LateEntryPage />)} />

      <Route path="/incidencias" element={guarded('incidencias.revisar', <IncidentsPage />)} />

      <Route
        path="/central-riesgo/:id?"
        element={guarded('riesgo.consultar', <RiskCenterPage />)}
      />

      <Route
        path="/reportes/intentos"
        element={guarded('administracion.gestionar', <AlienAttemptsReportPage />)}
      />

      <Route path="*" element={<Navigate to="/materias" replace />} />

      <Route path="*" element={<Navigate to="/materias" replace />} />

    </Routes>
  )
}

/**
 * Rutas del auxiliar: la consulta de sus exámenes y del ambiente que el docente le asignó (HU-09)
 * y el control de ingreso de los exámenes donde está habilitado (HU-11).
 */
function AuxiliarRoutes() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/mis-examenes" replace />} />
      <Route path="/mis-examenes" element={guarded('ingreso.operar', <MyAssignmentsPage />)} />
      <Route path="/control-ingreso" element={guarded('ingreso.operar', <OpenEntryControlsPage />)} />
      <Route
        path="/examenes/:examId/control-ingreso"
        element={guarded('ingreso.operar', <EntryControlPage />)}
      />
      <Route
        path="/examenes/en-curso"
        element={guarded('ingreso.operar', <InProgressExamsPage />)}
      />
      <Route
        path="/examenes/:examId/en-curso"
        element={guarded('ingreso.operar', <ExamInProgressPage extensions={examInProgressExtensions} />)}
      />
      <Route path={LATE_ENTRY_EXAMS_PATH} element={guarded('ingreso.operar', <LateEntryExamsPage />)} />
      <Route path={LATE_ENTRY_PATH} element={guarded('ingreso.operar', <LateEntryPage />)} />
      <Route
        path="/reportes/intentos"
        element={guarded('administracion.gestionar', <AlienAttemptsReportPage />)}
      />
      <Route path="*" element={<Navigate to="/mis-examenes" replace />} />
    </Routes>
  )
}

/** El detalle del curso pasó de `/cursos/:idGrupo` a `/grupos/:idGrupo`; los enlaces viejos siguen sirviendo. */
function CourseDetailRedirect() {
  const { idGrupo = '' } = useParams()
  const { search } = useLocation()

  return <Navigate to={{ pathname: `/grupos/${encodeURIComponent(idGrupo)}`, search }} replace />
}

/**
 * Las URLs anteriores de Materias (`/materias`, `?tab=`, `/materias/asignar`) llevan a la vista de
 * Asignaciones. Solo se conserva `?carrera=`: el resto era de las pestañas y ya no existe.
 */
function AssignmentsRedirect() {
  const [searchParams] = useSearchParams()
  const career = searchParams.get('carrera')

  const target =
    career === null
      ? '/materias/asignaciones'
      : `/materias/asignaciones?carrera=${encodeURIComponent(career)}`

  return <Navigate to={target} replace />
}

/**
 * Rutas de la administración del catálogo institucional.
 *
 * Cuentas, Materias (Asignaciones y Catálogo), Ambientes y la Central de riesgo (solo lectura)
 * tienen pantallas disponibles.
 * Facultades y carreras permanecen pendientes de sus respectivas historias de usuario.
 */
function AdministradorRoutes() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/cuentas/nueva" replace />} />
      <Route path="/cuentas" element={<Navigate to="/cuentas/nueva" replace />} />
      <Route
        path="/cuentas/nueva"
        element={guarded('administracion.gestionar', <RegistrarCuentaPage />)}
      />
      <Route
        path="/cuentas/roles"
        element={guarded('administracion.gestionar', <ModificarRolPage />)}
      />
      <Route
        path="/cuentas/:idUsuario"
        element={guarded('administracion.gestionar', <CuentaDetallePage />)}
      />
      <Route path="/ambientes" element={guarded('administracion.gestionar', <ClassroomsPage />)} />
      <Route path="/materias" element={<AssignmentsRedirect />} />
      <Route path="/materias/asignar" element={<AssignmentsRedirect />} />
      <Route
        path="/materias/asignaciones"
        element={guarded('administracion.gestionar', <AdminSubjectAssignmentsPage />)}
      />
      <Route
        path="/materias/catalogo"
        element={guarded('administracion.gestionar', <AdminSubjectCatalogPage />)}
      />
      <Route
        path="/central-riesgo/:id?"
        element={guarded('riesgo.consultar', <RiskCenterPage />)}
      />
      <Route
        path="/reportes/intentos"
        element={guarded('administracion.gestionar', <AlienAttemptsReportPage />)}
      />

      <Route path="*" element={<Navigate to="/cuentas/nueva" replace />} />
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

/**
 * Con la contraseña temporal pendiente (HU-001) la sesión solo sirve para cambiarla: cualquier otra
 * ruta, incluido /login, vuelve aquí. Escribir otra URL a mano tampoco la abre.
 */
function PasswordChangeRoutes() {
  return (
    <Routes>
      <Route path={CHANGE_PASSWORD_PATH} element={<CambiarContrasenaPage />} />
      <Route path="*" element={<Navigate to={CHANGE_PASSWORD_PATH} replace />} />
    </Routes>
  )
}

export function AppRouter() {
  const { estado, usuario } = useAuth()

  // Con un token guardado no se pinta nada hasta saber si sigue vivo: así la recarga de una
  // sesión válida no muestra por un instante la pantalla equivocada.
  if (estado === 'verificando') {
    return <LoadingState rows={3} label="Verificando la sesión" />
  }

  if (estado === 'autenticado' && usuario?.debe_cambiar_contrasenia) {
    return <PasswordChangeRoutes />
  }

  return (
    <Routes>
      <Route path="/login" element={<LoginRoute />} />
      <Route path="/*" element={<AreaRoutes />} />
    </Routes>
  )
}
