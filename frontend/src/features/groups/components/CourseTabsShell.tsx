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

import { GroupAssistantsTab } from './GroupAssistantsTab'
import { GroupExamsTab } from './GroupExamsTab'

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
}

/**
 * Armazón de pestañas del detalle del curso.
 *
 * HU-021 incorpora la carga de nómina dentro de la pestaña Nómina, respetando las condiciones
 * de grupo y período. HU-029 llena las pestañas Auxiliares y Exámenes con los datos del grupo.
 */
export function CourseTabsShell({
  group,
  subjectName,
  meta,
  onReload,
}: CourseTabsShellProps) {
  const rosterTitle = hasRoster(group) ? 'Nómina cargada' : 'Sin nómina cargada'
  const rosterDescription = hasRoster(group)
    ? `El grupo tiene ${group.cantidad_estudiantes} inscritos.`
    : 'Este grupo todavía no tiene estudiantes inscritos.'

  const tabs: TabDefinition[] = [
    {
      value: 'nomina',
      label: 'Nómina',
      icon: Users,
      count: group.cantidad_estudiantes,
    },
    {
      value: 'auxiliares',
      label: 'Auxiliares',
      icon: UsersRound,
    },
    {
      value: 'examenes',
      label: 'Exámenes',
      icon: CalendarClock,
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
              title={rosterTitle}
              description={rosterDescription}
            />
          </Card>
        )}
      </TabsContent>

      <TabsContent
        value="auxiliares"
        className="mt-4"
      >
        <GroupAssistantsTab groupId={group.id_grupo} />
      </TabsContent>

      <TabsContent
        value="examenes"
        className="mt-4"
      >
        <GroupExamsTab groupId={group.id_grupo} />
      </TabsContent>
    </Tabs>
  )
}