import { AppShell } from '@/components/layout/AppShell'
import { PageHeader } from '@/components/common/PageHeader'
import { SubjectCareerAssignmentForm } from '../components/SubjectCareerAssignmentForm'

/**
 * Vista administrativa para asignar una materia existente
 * del catálogo institucional a una carrera activa.
 */
export function SubjectCareerAssignmentPage() {
  return (
    <AppShell
      mobileTitle="Asignar materia"
      breadcrumbs={[
        { label: 'Administración' },
        { label: 'Materias' },
        { label: 'Asignar materia' },
      ]}
    >
      <PageHeader
        title="Asignar materia a carrera"
        subtitle="Vincula una materia existente del catálogo institucional con una carrera activa."
      />

      <SubjectCareerAssignmentForm />
    </AppShell>
  )
}