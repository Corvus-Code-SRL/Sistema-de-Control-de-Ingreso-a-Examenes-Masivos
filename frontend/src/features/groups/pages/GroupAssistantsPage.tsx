import { useState } from 'react'
import { UserPlus } from 'lucide-react'
import { PageHeader } from '@/components/common/PageHeader'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Toast } from '@/components/ui/toast'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import { AssignAssistantModal, assistantsService } from '@/features/assistants'
import { GroupContextLayout } from '../components/GroupContextLayout'
import { useGroupAssistants } from '../hooks/useGroupAssistants'
import { courseTitle, type GroupDetail } from '../types/group.types'
import { groupDetailPath } from '../utils/paths'

export function GroupAssistantsPage() {
  return (
    <GroupContextLayout title="Auxiliares del grupo">
      {(detail) => <GroupAssistantsView detail={detail} />}
    </GroupContextLayout>
  )
}

function GroupAssistantsView({ detail }: { detail: GroupDetail }) {
  const groupId = detail.group.id_grupo
  const resource = useGroupAssistants(groupId)
  const [isAssignOpen, setIsAssignOpen] = useState(false)
  const [assigned, setAssigned] = useState(false)
  const context = `${courseTitle(detail.subject, detail.group)} · ${detail.subject.carrera.nombre} · ${detail.group.periodo.etiqueta}`

  async function assign(userId: string) {
    await assistantsService.addToGroup(groupId, userId)
    setIsAssignOpen(false)
    resource.reload()
    setAssigned(true)
  }

  return (
    <>
      <PageHeader
        title="Auxiliares del grupo"
        subtitle={context}
        backTo={groupDetailPath(groupId)}
        backLabel="Volver al grupo"
        actions={
          <Button onClick={() => setIsAssignOpen(true)}>
            <UserPlus className="size-4" aria-hidden="true" />
            Asignar auxiliar
          </Button>
        }
      />

      {assigned && (
        <Toast title="Auxiliar asignado al grupo" description={context} onClose={() => setAssigned(false)} />
      )}

      <Card className="overflow-hidden p-0">
        {resource.status === 'loading' && (
          <LoadingState rows={3} label="Cargando los auxiliares del grupo" />
        )}
        {resource.status === 'error' && resource.error && (
          <ErrorState error={resource.error} onRetry={resource.reload} />
        )}
        {resource.status === 'success' && resource.data?.length === 0 && (
          <EmptyState
            icon={UserPlus}
            title="Este grupo aún no tiene auxiliares"
            description="Asigne un auxiliar para incorporarlo a este grupo."
          />
        )}
        {resource.status === 'success' && (resource.data?.length ?? 0) > 0 && (
          <>
            <div className="hidden md:block">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Auxiliar</TableHead>
                    <TableHead>Código SIS</TableHead>
                    <TableHead>Incorporación</TableHead>
                    <TableHead>Exámenes habilitados</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {resource.data?.map((assistant) => (
                    <TableRow key={assistant.id_usuario}>
                      <TableCell>
                        <div className="flex items-center gap-3">
                          <Avatar className="size-8 shrink-0" aria-hidden="true">
                            <AvatarFallback className="bg-brand text-xs font-medium text-primary-foreground">
                              {initialsOf(assistant.nombre_completo)}
                            </AvatarFallback>
                          </Avatar>
                          <span className="font-medium">{assistant.nombre_completo}</span>
                        </div>
                      </TableCell>
                      <TableCell className="sciem-tnum">{assistant.cod_sis}</TableCell>
                      <TableCell className="sciem-tnum">{assistant.fecha_incorporacion}</TableCell>
                      <TableCell>
                        {assistant.examenes.length === 0 ? (
                          <span className="text-muted-foreground">Sin habilitar</span>
                        ) : (
                          <ul aria-label={`Exámenes habilitados de ${assistant.nombre_completo}`} className="flex flex-wrap gap-1">
                            {assistant.examenes.map((exam) => (
                              <li key={exam.id_examen} className="max-w-full break-words rounded-md bg-warn-soft px-2 py-0.5 text-xs font-medium text-warn-fg">
                                {exam.nombre_examen}
                              </li>
                            ))}
                          </ul>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
            <ul aria-label="Auxiliares del grupo" className="md:hidden">
              {resource.data?.map((assistant) => (
                <li key={assistant.id_usuario} className="space-y-3 border-b border-border px-4 py-3 last:border-b-0">
                  <div className="flex items-start gap-3">
                    <Avatar className="size-8 shrink-0" aria-hidden="true">
                      <AvatarFallback className="bg-brand text-xs font-medium text-primary-foreground">
                        {initialsOf(assistant.nombre_completo)}
                      </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0 flex-1">
                      <h2 className="break-words text-sm font-medium">{assistant.nombre_completo}</h2>
                      <p className="sciem-tnum break-words text-xs text-muted-foreground">SIS {assistant.cod_sis}</p>
                    </div>
                  </div>
                  <dl className="space-y-2 text-xs">
                    <div>
                      <dt className="text-muted-foreground">Incorporación</dt>
                      <dd className="sciem-tnum">
                        <time dateTime={assistant.fecha_incorporacion}>{assistant.fecha_incorporacion}</time>
                      </dd>
                    </div>
                    <div>
                      <dt className="mb-1 text-muted-foreground">Exámenes habilitados</dt>
                      <dd>
                        {assistant.examenes.length === 0 ? (
                          <span className="text-muted-foreground">Sin habilitar</span>
                        ) : (
                          <ul aria-label={`Exámenes habilitados de ${assistant.nombre_completo}`} className="flex flex-wrap gap-1">
                            {assistant.examenes.map((exam) => (
                              <li key={exam.id_examen} className="max-w-full break-words rounded-md bg-warn-soft px-2 py-0.5 font-medium text-warn-fg">
                                {exam.nombre_examen}
                              </li>
                            ))}
                          </ul>
                        )}
                      </dd>
                    </div>
                  </dl>
                </li>
              ))}
            </ul>
          </>
        )}
      </Card>

      <AssignAssistantModal
        open={isAssignOpen}
        contextGroup={{ id_grupo: groupId, label: context }}
        assignedUserIds={resource.data?.map((assistant) => assistant.id_usuario) ?? []}
        onConfirm={assign}
        onCancel={() => setIsAssignOpen(false)}
      />
    </>
  )
}

function initialsOf(fullName: string): string {
  return fullName.trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase()
}
