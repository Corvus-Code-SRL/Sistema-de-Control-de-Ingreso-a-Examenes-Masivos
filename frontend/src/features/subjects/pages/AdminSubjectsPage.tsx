import { BookOpen } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Card } from '@/components/ui/card'
import { AdminSubjectsTable } from '../components/AdminSubjectsTable'
import { useAdminSubjects } from '../hooks/useAdminSubjects'

/**
 * Catálogo institucional de materias para Administración.
 *
 * HU-007 permite seleccionar una materia existente y acceder a su edición.
 * El registro de nuevas materias pertenece a HU-006 y no se integra aquí.
 */
export function AdminSubjectsPage() {
  const { subjects, status, error, reload } = useAdminSubjects()

  return (
    <AppShell
      mobileTitle="Materias"
      breadcrumbs={[
        { label: 'Administración' },
        { label: 'Materias' },
      ]}
    >
      <PageHeader
        title="Materias"
        subtitle="Catálogo institucional de materias disponibles para administración."
      />

      <Card className="overflow-hidden p-0">
        {status === 'loading' && (
          <LoadingState rows={5} label="Cargando materias" />
        )}

        {status === 'error' && error && (
          <ErrorState error={error} onRetry={reload} />
        )}

        {status === 'success' && subjects.length === 0 && (
          <EmptyState
            icon={BookOpen}
            title="Aún no hay materias registradas"
            description="El catálogo institucional todavía no contiene materias."
          />
        )}

        {status === 'success' && subjects.length > 0 && (
          <AdminSubjectsTable subjects={subjects} />
        )}
      </Card>
    </AppShell>
  )
}