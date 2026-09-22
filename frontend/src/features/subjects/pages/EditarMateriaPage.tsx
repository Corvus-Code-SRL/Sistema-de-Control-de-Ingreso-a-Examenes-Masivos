import { BookOpen } from 'lucide-react'
import { useNavigate, useParams } from 'react-router-dom'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Card } from '@/components/ui/card'
import { SubjectUpdateForm } from '../components/SubjectUpdateForm'
import { useAdminSubjects } from '../hooks/useAdminSubjects'

/**
 * Edición de una materia existente del catálogo institucional.
 *
 * La materia se obtiene desde el catálogo administrativo y únicamente
 * pueden modificarse su nombre y código.
 */
export function EditarMateriaPage() {
  const navigate = useNavigate()
  const { idMateria } = useParams<{ idMateria: string }>()

  const { subjects, status, error, reload } = useAdminSubjects()

  const parsedId = Number(idMateria)

  const subject =
    Number.isInteger(parsedId) && parsedId > 0
      ? subjects.find((item) => item.id_materia === parsedId)
      : undefined

  function returnToCatalog() {
    navigate('/materias')
  }

  return (
    <AppShell
      mobileTitle="Editar materia"
      breadcrumbs={[
        { label: 'Administración' },
        { label: 'Materias' },
        { label: 'Editar materia' },
      ]}
    >
      <PageHeader
        title="Editar materia"
        subtitle="Actualice el nombre o código de la materia seleccionada."
        backTo="/materias"
        backLabel="Volver a Materias"
      />

      <Card className="p-6">
        {status === 'loading' && (
          <LoadingState
            rows={3}
            label="Cargando materia"
          />
        )}

        {status === 'error' && error && (
          <ErrorState
            error={error}
            onRetry={reload}
          />
        )}

        {status === 'success' && !subject && (
          <EmptyState
            icon={BookOpen}
            title="Materia no encontrada"
            description="La materia solicitada no existe en el catálogo institucional."
          />
        )}

        {status === 'success' && subject && (
          <SubjectUpdateForm
            subject={subject}
            onCancel={returnToCatalog}
            onUpdated={returnToCatalog}
          />
        )}
      </Card>
    </AppShell>
  )
}