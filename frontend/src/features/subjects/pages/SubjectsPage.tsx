import { BookOpen } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { DataPagination } from '@/components/common/DataPagination'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Card } from '@/components/ui/card'
import { SubjectListItem } from '../components/SubjectListItem'
import { SubjectsTable } from '../components/SubjectsTable'
import { useSubjectCatalog } from '../hooks/useSubjectCatalog'
import { subjectCareerKey } from '../types/subject.types'

/**
 * Catálogo institucional de materias (artboard 1.1).
 *
 * Es el punto de entrada a los grupos: lista todos los pares materia-carrera de
 * la institución y solo abre los que el docente dicta.
 */
export function SubjectsPage() {
  const { subjects, page, isLoading, isEmpty, error, currentPage, goToPage, reload } =
    useSubjectCatalog()

  return (
    <AppShell
      mobileTitle="Materias"
      breadcrumbs={[{ label: 'Gestión académica' }, { label: 'Materias' }]}
      period={page?.meta.id_periodo_activo ? String(page.meta.id_periodo_activo) : undefined}
    >
      <PageHeader
        title="Materias"
        subtitle="Catálogo institucional. Abra una de sus materias para consultar sus grupos."
      />

      <Card className="overflow-hidden p-0">
        {isLoading && <LoadingState rows={6} label="Cargando el catálogo de materias" />}

        {!isLoading && error && <ErrorState error={error} onRetry={reload} />}

        {!isLoading && !error && isEmpty && (
          <EmptyState
            icon={BookOpen}
            title="No hay materias en el catálogo"
            description={
              page?.mensaje ?? 'No hay materias disponibles en el catálogo institucional.'
            }
          />
        )}

        {!isLoading && !error && !isEmpty && (
          <>
            <div className="hidden md:block">
              <SubjectsTable subjects={subjects} />
            </div>

            <ul className="md:hidden">
              {subjects.map((subject) => (
                <SubjectListItem key={subjectCareerKey(subject)} subject={subject} />
              ))}
            </ul>

            {page && (
              <DataPagination
                page={currentPage}
                perPage={page.perPage}
                total={page.total}
                totalPages={page.totalPages}
                onPageChange={goToPage}
                itemLabel="materias"
              />
            )}
          </>
        )}
      </Card>
    </AppShell>
  )
}
