import { useState } from 'react'
import { Users } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Card } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { EXCEPTIONAL_MESSAGES } from '../components/exceptionalStudentMessages'
import { ExceptionalStudentWorkspace } from '../components/ExceptionalStudentWorkspace'
import { useExceptionalGroups } from '../hooks/useExceptionalGroups'

/**
 * Registrar estudiante excepcional (HU-023): acceso independiente desde el menú. Elige el grupo con
 * una lista propia (los del docente en el período vigente), busca al estudiante por código SIS y
 * lo registra con su motivo.
 */
export function ExceptionalStudentPage() {
  const groups = useExceptionalGroups()
  const [selectedId, setSelectedId] = useState<number | ''>('')
  const list = groups.data ?? []
  const selected = list.find((group) => group.id_grupo === selectedId) ?? null

  return (
    <AppShell
      mobileTitle="Estudiante excepcional"
      breadcrumbs={[{ label: 'Registrar estudiante excepcional' }]}
    >
      <PageHeader
        title="Registrar estudiante excepcional"
        subtitle="Agregue a uno de sus grupos a un estudiante que no figura en su nómina, como una convalidación o una inscripción tardía."
      />

      {groups.error && <ErrorState error={groups.error} onRetry={groups.reload} />}

      {!groups.error && groups.data === null && (
        <LoadingState rows={3} label="Cargando sus grupos" />
      )}

      {groups.data && list.length === 0 && (
        <Card>
          <EmptyState
            icon={Users}
            title={EXCEPTIONAL_MESSAGES.emptyTitle}
            description={EXCEPTIONAL_MESSAGES.emptyDescription}
          />
        </Card>
      )}

      {list.length > 0 && (
        <Card className="space-y-2 p-4">
          <Label htmlFor="exceptional-group">Grupo</Label>
          <select
            id="exceptional-group"
            value={selectedId}
            onChange={(event) =>
              setSelectedId(event.target.value === '' ? '' : Number(event.target.value))
            }
            className="h-11 w-full rounded-md border border-input bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:w-96"
          >
            <option value="">Elija un grupo</option>
            {list.map((group) => (
              <option key={group.id_grupo} value={group.id_grupo}>
                {group.label}
              </option>
            ))}
          </select>
        </Card>
      )}

      {selected && <ExceptionalStudentWorkspace key={selected.id_grupo} group={selected} />}
    </AppShell>
  )
}
