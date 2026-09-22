import { CalendarClock, Users, UsersRound } from 'lucide-react'

import type { LucideIcon } from 'lucide-react'

import { EmptyState } from '@/components/common/EmptyState'
import { Card } from '@/components/ui/card'
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '@/components/ui/tabs'

import { RosterUploadPanel } from '@/features/students'

import {
  hasRoster,
  type Group,
  type GroupDetailMeta,
} from '../types/group.types'

interface CourseTabsShellProps {
  group: Group
  subjectName: string
  meta: GroupDetailMeta
  onReload: () => void | Promise<unknown>
}

interface TabDefinition {
  value: string
  label: string
  icon: LucideIcon
  count?: number
  title: string
  description: string
}

/**
 * Armazón de pestañas del detalle del curso.
 *
 * HU-021 incorpora la carga de nómina dentro de la pestaña Nómina,
 * respetando las condiciones de grupo y período.
 */
export function CourseTabsShell({
  group,
  subjectName,
  meta,
  onReload,
}: CourseTabsShellProps) {
  const tabs: TabDefinition[] = [
    {
      value: 'nomina',
      label: 'Nómina',
      icon: Users,
      count: group.cantidad_estudiantes,
      title: hasRoster(group)
        ? 'Nómina cargada'
        : 'Sin nómina cargada',
      description: hasRoster(group)
        ? `El grupo tiene ${group.cantidad_estudiantes} inscritos.`
        : 'Este grupo todavía no tiene estudiantes inscritos.',
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

  const canUploadRoster =
    group.es_mio &&
    group.activo &&
    meta.es_periodo_activo

  return (
    <Tabs
      defaultValue="nomina"
      className="w-full"
    >
      <TabsList className="w-full justify-start overflow-x-auto">
        {tabs.map((tab) => (
          <TabsTrigger
            key={tab.value}
            value={tab.value}
            className="min-w-fit px-4"
          >
            <tab.icon aria-hidden="true" />

            <span>{tab.label}</span>

            {tab.count !== undefined && (
              <span className="sciem-tnum rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                {tab.count}
              </span>
            )}
          </TabsTrigger>
        ))}
      </TabsList>

      <TabsContent
        value="nomina"
        className="mt-4"
      >
        {canUploadRoster ? (
          <RosterUploadPanel
            key={group.id_grupo}
            group={group}
            subjectName={subjectName}
            onReload={onReload}
          />
        ) : (
          <Card className="p-0">
            <EmptyState
              icon={tabs[0].icon}
              title={tabs[0].title}
              description={tabs[0].description}
            />
          </Card>
        )}
      </TabsContent>

      {tabs.slice(1).map((tab) => (
        <TabsContent
          key={tab.value}
          value={tab.value}
          className="mt-4"
        >
          <Card className="p-0">
            <EmptyState
              icon={tab.icon}
              title={tab.title}
              description={tab.description}
            />
          </Card>
        </TabsContent>
      ))}
    </Tabs>
  )
}