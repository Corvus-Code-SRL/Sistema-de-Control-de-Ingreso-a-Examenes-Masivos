import { GraduationCap } from 'lucide-react'
import { Link } from 'react-router-dom'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { CourseCard } from '../components/CourseCard'
import { useMyCourses } from '../hooks/useMyCourses'

/**
 * Cursos del docente (artboards 2.1 y 2.2).
 *
 * Reúne sus grupos de todas las carreras y facultades, que es la vista que no
 * ofrece el catálogo: allí el recorrido empieza por la materia.
 */
export function MyCoursesPage() {
  const { courses, isLoading, isEmpty, error, reload } = useMyCourses()

  return (
    <AppShell
      mobileTitle="Mis cursos"
      mobileSubtitle={
        courses.length > 0
          ? `${courses.length} ${courses.length === 1 ? 'grupo' : 'grupos'}`
          : undefined
      }
      breadcrumbs={[{ label: 'Gestión académica' }, { label: 'Mis cursos' }]}
    >
      <PageHeader
        title="Mis cursos"
        subtitle="Sus grupos del período vigente en todas las carreras. Abra un curso para ver su nómina, auxiliares y exámenes."
        actions={
          <Button variant="outline" size="sm" asChild>
            <Link to="/materias">Ir a Materias</Link>
          </Button>
        }
      />

      {isLoading && (
        <Card className="p-0">
          <LoadingState rows={4} label="Cargando sus cursos" />
        </Card>
      )}

      {!isLoading && error && (
        <Card className="p-0">
          <ErrorState error={error} onRetry={reload} />
        </Card>
      )}

      {!isLoading && !error && isEmpty && (
        <Card className="p-0">
          <EmptyState
            icon={GraduationCap}
            title="Aún no tiene cursos"
            description="Los cursos aparecen cuando usted dicta un grupo en alguna de sus materias. Revise el catálogo para ver las materias a su cargo."
            action={
              <Button variant="outline" size="sm" asChild>
                <Link to="/materias">Ir a Materias</Link>
              </Button>
            }
          />
        </Card>
      )}

      {!isLoading && !error && !isEmpty && (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {courses.map((course) => (
            <CourseCard key={course.group.id_grupo} course={course} />
          ))}
        </div>
      )}
    </AppShell>
  )
}
