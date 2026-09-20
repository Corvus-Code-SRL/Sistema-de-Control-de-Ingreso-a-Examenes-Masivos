import { CalendarClock, Users, UsersRound } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { Card } from '@/components/ui/card'
import { EmptyState } from '@/components/common/EmptyState'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { hasRoster, type Group } from '../types/group.types'

interface CourseTabsShellProps {
  group: Group
}

interface TabDefinition {
  value: string
  label: string
  icon: LucideIcon
  /** Conteo a la derecha de la pestaña; solo la nómina lo tiene en esta historia. */
  count?: number
  title: string
  description: string
}

/**
 * Armazón de pestañas del detalle del curso (artboards 2.3, 2.4 y 2.11).
 *
 * Esta historia aporta la estructura y el permiso de acceso. El contenido de
 * cada pestaña llega con HU-020 (nómina), HU-021 (carga de nómina) y HU-029
 * (exámenes), así que los paneles quedan anunciados y vacíos en lugar de
 * mostrar datos a medias.
 */
export function CourseTabsShell({ group }: CourseTabsShellProps) {
  const tabs: TabDefinition[] = [
    {
      value: 'nomina',
      label: 'Nómina',
      icon: Users,
      count: group.cantidad_estudiantes,
      title: hasRoster(group) ? 'Nómina pendiente de implementar' : 'Sin nómina cargada',
      description: hasRoster(group)
        ? `El grupo tiene ${group.cantidad_estudiantes} inscritos. El listado de estudiantes se incorpora con la historia de consulta de nómina.`
        : 'Este grupo todavía no tiene estudiantes inscritos. La carga de la nómina se incorpora con su propia historia.',
    },
    {
      value: 'auxiliares',
      label: 'Auxiliares',
      icon: UsersRound,
      title: 'Auxiliares del grupo',
      description:
        'La asignación y consulta de auxiliares se incorpora con la historia correspondiente.',
    },
    {
      value: 'examenes',
      label: 'Exámenes',
      icon: CalendarClock,
      title: 'Exámenes del grupo',
      description:
        'Los exámenes programados y finalizados de este grupo se incorporan con la historia de programación de exámenes.',
    },
  ]

  return (
    <Tabs defaultValue="nomina" className="gap-4">
      <TabsList>
        {tabs.map((tab) => (
          <TabsTrigger key={tab.value} value={tab.value}>
            {tab.label}
            {tab.count !== undefined && (
              <span className="ml-1.5 text-xs tabular-nums text-muted-foreground">
                {tab.count}
              </span>
            )}
          </TabsTrigger>
        ))}
      </TabsList>

      {tabs.map((tab) => (
        <TabsContent key={tab.value} value={tab.value}>
          <Card className="p-0">
            <EmptyState icon={tab.icon} title={tab.title} description={tab.description} />
          </Card>
        </TabsContent>
      ))}
    </Tabs>
  )
}
