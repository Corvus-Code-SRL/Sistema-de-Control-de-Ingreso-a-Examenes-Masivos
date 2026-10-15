import { AppShell } from '@/components/layout/AppShell'
import { PageHeader } from '@/components/common/PageHeader'
import { SubjectCatalogView } from '../components/SubjectCatalogView'

/**
 * Catálogo institucional de materias para Administración, de solo lectura.
 *
 * Vista de apoyo sin historia propia: las materias no se crean ni se editan desde la aplicación.
 */
export function AdminSubjectCatalogPage() {
  return (
    <AppShell
      mobileTitle="Catálogo"
      breadcrumbs={[
        { label: 'Administración' },
        { label: 'Materias' },
        { label: 'Catálogo' },
      ]}
    >
      <PageHeader
        title="Catálogo de materias"
        subtitle="Materias del catálogo institucional, de solo lectura."
      />

      <SubjectCatalogView />
    </AppShell>
  )
}
