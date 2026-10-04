import { useState } from 'react'
import { Search, UserPlus } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { AssistantRowMenu } from '../components/AssistantRowMenu'
import { AssignAssistantModal } from '../components/AssignAssistantModal'
import { MoveAssistantModal } from '../components/MoveAssistantModal'
import { RemoveAssistantFromGroupModal } from '../components/RemoveAssistantFromGroupModal'
import { EnableAssistantExamModal } from '../components/EnableAssistantExamModal'
import { RemoveAssistantFromExamModal } from '../components/RemoveAssistantFromExamModal'
import { assistantsService } from '../services/assistantsService'
import type { AssistantWithGroups } from '../types/assistant.types'

export function MyAssistantsPage() {
  const assistantsResource = useAsyncResource<AssistantWithGroups[]>(
    (signal) => assistantsService.listMine(signal),
    []
  )

  const groupsResource = useAsyncResource(
    (signal) => assistantsService.listMyGroups(signal),
    []
  )

  const assistants = assistantsResource.data ?? []
  const availableGroups = groupsResource.data ?? []
  const isEmpty = assistantsResource.status === 'success' && assistants.length === 0

  const [search, setSearch] = useState('')

  const [isAssignOpen, setIsAssignOpen] = useState(false)
  const [assistantToMove, setAssistantToMove] = useState<AssistantWithGroups | null>(null)
  const [assistantToRemove, setAssistantToRemove] = useState<AssistantWithGroups | null>(null)
  const [assistantToEnable, setAssistantToEnable] = useState<AssistantWithGroups | null>(null)
  const [assistantToRemoveExam, setAssistantToRemoveExam] =
    useState<AssistantWithGroups | null>(null)

  const filteredAssistants =
    search.trim() === ''
      ? assistants
      : assistants.filter(
          (a) =>
            a.nombre_completo.toLowerCase().includes(search.toLowerCase()) ||
            a.cod_sis.includes(search)
        )

  async function handleAssign(userId: string, groupIds: number[]) {
    await assistantsService.addToGroups(userId, groupIds)
    setIsAssignOpen(false)
    assistantsResource.reload()
  }

  async function handleMove(sourceGroupId: number, targetGroupId: number) {
    if (!assistantToMove) return

    await assistantsService.moveBetweenGroups({
      id_usuario: assistantToMove.id_usuario,
      id_grupo_origen: sourceGroupId,
      id_grupo_destino: targetGroupId,
    })

    setAssistantToMove(null)
    assistantsResource.reload()
  }

  async function handleRemove(groupId: number) {
    if (!assistantToRemove) return

    await assistantsService.removeFromGroup(groupId, assistantToRemove.id_usuario)

    setAssistantToRemove(null)
    assistantsResource.reload()
  }

  async function handleEnableForExam(examId: number) {
    if (!assistantToEnable) return

    await assistantsService.enableForExam(examId, assistantToEnable.id_usuario)

    setAssistantToEnable(null)
    assistantsResource.reload()
  }

  async function handleRemoveFromExam(examId: number) {
    if (!assistantToRemoveExam) return

    await assistantsService.removeFromExam(examId, assistantToRemoveExam.id_usuario)

    setAssistantToRemoveExam(null)
    assistantsResource.reload()
  }

  return (
    <AppShell
      mobileTitle="Mis auxiliares"
      breadcrumbs={[{ label: 'Gestión académica' }, { label: 'Mis auxiliares' }]}
    >
      <PageHeader
        title="Mis auxiliares"
        subtitle="Auxiliares asignados a sus grupos. Las cuentas las crea el administrador."
        actions={
          <Button onClick={() => setIsAssignOpen(true)} className="gap-1.5">
            <UserPlus className="size-4" aria-hidden="true" />
            Asignar auxiliar
          </Button>
        }
      />

      <div className="relative">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
        <Input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Nombre, código SIS o grupo"
          className="pl-9"
        />
      </div>

      <Card className="p-0">
        {assistantsResource.status === 'loading' && (
          <LoadingState rows={4} label="Cargando sus auxiliares" />
        )}

        {assistantsResource.status === 'error' && assistantsResource.error && (
          <ErrorState
            error={assistantsResource.error}
            onRetry={assistantsResource.reload}
          />
        )}

        {isEmpty && (
          <EmptyState
            icon={UserPlus}
            title="Aún no tiene auxiliares"
            description="Asigne auxiliares a sus grupos para que le ayuden durante los exámenes."
            action={
              <Button onClick={() => setIsAssignOpen(true)} className="gap-1.5">
                <UserPlus className="size-4" aria-hidden="true" />
                Asignar auxiliar
              </Button>
            }
          />
        )}

        {!isEmpty && assistantsResource.status === 'success' && (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Auxiliar</TableHead>
                <TableHead>Código SIS</TableHead>
                <TableHead>Grupos</TableHead>
                <TableHead>Exámenes</TableHead>
                <TableHead className="w-12" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredAssistants.map((assistant) => (
                <TableRow key={assistant.id_usuario}>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand text-brand-foreground text-xs font-medium">
                        {initialsOf(assistant)}
                      </span>
                      <span className="font-medium">{assistant.nombre_completo}</span>
                    </div>
                  </TableCell>
                  <TableCell className="font-mono text-sm">
                    {assistant.cod_sis}
                  </TableCell>
                  <TableCell>
                    <div className="flex flex-wrap gap-1">
                      {assistant.grupos.map((g) => (
                        <span
                          key={g.id_grupo}
                          className="inline-flex items-center rounded-md bg-brand-soft px-2 py-0.5 text-xs font-medium text-brand"
                        >
                          {g.label}
                        </span>
                      ))}
                    </div>
                  </TableCell>
                  <TableCell>
                    {assistant.examenes.length === 0 ? (
                      <span className="text-xs text-muted-foreground">Sin habilitar</span>
                    ) : (
                      <div className="flex flex-wrap gap-1">
                        {assistant.examenes.map((e) => (
                          <span
                            key={e.id_examen}
                            className="inline-flex items-center rounded-md bg-warn-soft px-2 py-0.5 text-xs font-medium text-warn-fg"
                          >
                            {e.nombre_examen}
                          </span>
                        ))}
                      </div>
                    )}
                  </TableCell>
                  <TableCell>
                    <AssistantRowMenu
                      onMove={() => setAssistantToMove(assistant)}
                      onEnableForExam={() => setAssistantToEnable(assistant)}
                      onRemoveFromExam={() => setAssistantToRemoveExam(assistant)}
                      onRemove={() => setAssistantToRemove(assistant)}
                      hasExams={assistant.examenes.length > 0}
                    />
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Card>

      <AssignAssistantModal
        open={isAssignOpen}
        groups={availableGroups}
        existingAssignments={assistants}
        onConfirm={handleAssign}
        onCancel={() => setIsAssignOpen(false)}
      />

      <MoveAssistantModal
        open={assistantToMove !== null}
        assistant={assistantToMove}
        availableGroups={availableGroups}
        onConfirm={handleMove}
        onCancel={() => setAssistantToMove(null)}
      />

      <RemoveAssistantFromGroupModal
        open={assistantToRemove !== null}
        assistant={assistantToRemove}
        onConfirm={handleRemove}
        onCancel={() => setAssistantToRemove(null)}
      />

      <EnableAssistantExamModal
        open={assistantToEnable !== null}
        assistant={assistantToEnable}
        onConfirm={handleEnableForExam}
        onCancel={() => setAssistantToEnable(null)}
      />

      <RemoveAssistantFromExamModal
        open={assistantToRemoveExam !== null}
        assistant={assistantToRemoveExam}
        onConfirm={handleRemoveFromExam}
        onCancel={() => setAssistantToRemoveExam(null)}
      />
    </AppShell>
  )
}

function initialsOf(assistant: AssistantWithGroups): string {
  const n = assistant.nombre?.[0] ?? ''
  const a = assistant.apellido_paterno?.[0] ?? ''
  return (n + a).toUpperCase()
}