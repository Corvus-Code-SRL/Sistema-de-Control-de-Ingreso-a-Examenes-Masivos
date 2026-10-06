import { useSearchParams } from 'react-router-dom'
import { AppShell } from '@/components/layout/AppShell'
import { PageHeader } from '@/components/common/PageHeader'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { SubjectAssignmentsTab } from '../components/SubjectAssignmentsTab'
import { SubjectCatalogTab } from '../components/SubjectCatalogTab'

const TAB_PARAM = 'tab'

type SubjectsTab = 'catalogo' | 'asignaciones'

/** La pestaña vive en la URL (`?tab=asignaciones`) para que se pueda compartir y recargar. */
function tabFrom(value: string | null): SubjectsTab {
  return value === 'asignaciones' ? 'asignaciones' : 'catalogo'
}

/**
 * Materias de Administración, en dos pestañas.
 *
 * Catálogo: lista de solo lectura del catálogo institucional (las materias no se crean ni se editan
 * desde la aplicación). Asignaciones: vincula materias a carreras activas y lista los pares.
 */
export function AdminSubjectsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const tab = tabFrom(searchParams.get(TAB_PARAM))

  const changeTab = (value: string) => {
    setSearchParams(value === 'asignaciones' ? { [TAB_PARAM]: 'asignaciones' } : {})
  }

  return (
    <AppShell
      mobileTitle="Materias"
      breadcrumbs={[{ label: 'Administración' }, { label: 'Materias' }]}
    >
      <PageHeader
        title="Materias"
        subtitle="Catálogo institucional de materias y su asignación a las carreras."
      />

      <Tabs value={tab} onValueChange={changeTab}>
        <TabsList>
          <TabsTrigger value="catalogo">Catálogo</TabsTrigger>
          <TabsTrigger value="asignaciones">Asignaciones</TabsTrigger>
        </TabsList>

        <TabsContent value="catalogo">
          <SubjectCatalogTab />
        </TabsContent>

        <TabsContent value="asignaciones">
          <SubjectAssignmentsTab />
        </TabsContent>
      </Tabs>
    </AppShell>
  )
}
