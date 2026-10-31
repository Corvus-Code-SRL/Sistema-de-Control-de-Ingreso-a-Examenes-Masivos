import { AppShell } from '@/components/layout/AppShell'
import { PageHeader } from '@/components/common/PageHeader'
import { SubjectAssignmentsView } from '../components/SubjectAssignmentsView'

/**
 * HU-006 · Asignar materia a una carrera.
 *
 * Una sola historia, una sola vista: el Administrador ofrece materias existentes a una carrera en
 * un período académico y consulta lo ofrecido. El catálogo de materias es otra vista.
 */
export function AdminSubjectAssignmentsPage() {
  return (
    <AppShell
      mobileTitle="Asignaciones"
      breadcrumbs={[
        { label: 'Administración' },
        { label: 'Materias' },
        { label: 'Asignaciones' },
      ]}
    >
      <PageHeader
        title="Asignaciones de materias"
        subtitle="Ofrece materias del catálogo institucional a las carreras en un período académico."
      />

      <SubjectAssignmentsView />
    </AppShell>
  )
}
