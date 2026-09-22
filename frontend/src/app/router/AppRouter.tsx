import { Navigate, Route, Routes } from 'react-router-dom'
import { useCurrentUser } from '@/features/auth'
import { CreateExamPage, ExamDetailPage, ExamsPage } from '@/features/exams'
import { CourseDetailPage, MyCoursesPage, SubjectGroupsPage } from '@/features/groups'
import {
  AdminSubjectsPage,
  EditarMateriaPage,
  SubjectsPage,
} from '@/features/subjects'
import { CuentaDetallePage, CuentasPage } from '@/features/users'

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
      <Route path="/materias" element={<SubjectsPage />} />
      <Route
        path="/carreras/:idCarrera/materias/:idMateria/grupos"
        element={<SubjectGroupsPage />}
      />
      <Route path="/mis-cursos" element={<MyCoursesPage />} />
      <Route path="/cursos/:idGrupo" element={<CourseDetailPage />} />
      <Route path="/examenes/nuevo" element={<CreateExamPage />} />
      <Route path="/examenes/programados" element={<ExamsPage />} />
      <Route path="/examenes/:examId" element={<ExamDetailPage />} />
      <Route path="*" element={<Navigate to="/materias" replace />} />
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
      <Route path="/cuentas" element={<CuentasPage />} />
      <Route path="/cuentas/:idUsuario" element={<CuentaDetallePage />} />

      <Route path="/materias" element={<AdminSubjectsPage />} />
      <Route
        path="/materias/:idMateria/editar"
        element={<EditarMateriaPage />}
      />

      <Route path="*" element={<Navigate to="/cuentas" replace />} />
    </Routes>
  )
}

/**
 * Cada área tiene su propio juego de rutas, incluido su destino por defecto.
 *
 * Esto no es una guarda de seguridad: mientras no exista autenticación, el área
 * la elige a mano quien desarrolla. Separarlas evita que una URL de un área
 * caiga en la pantalla de la otra.
 */
export function AppRouter() {
  const { area } = useCurrentUser()

  return area === 'administrador' ? <AdministradorRoutes /> : <DocenteRoutes />
}
