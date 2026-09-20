import { Navigate, Route, Routes } from 'react-router-dom'
import { CourseDetailPage, MyCoursesPage, SubjectGroupsPage } from '@/features/groups'
import { SubjectsPage } from '@/features/subjects'

/**
 * Rutas de la gestión académica del docente.
 *
 * El contexto de trabajo viaja en la URL como par carrera-materia, nunca como
 * materia sola: es lo que permite compartir o recargar una vista sin perderlo.
 */
export function AppRouter() {
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
      <Route path="*" element={<Navigate to="/materias" replace />} />
    </Routes>
  )
}
