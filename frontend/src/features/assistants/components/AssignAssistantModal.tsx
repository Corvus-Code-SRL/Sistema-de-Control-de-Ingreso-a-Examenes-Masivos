import { useState, type FormEvent } from 'react'
import { Search, AlertCircle } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { assistantsService } from '../services/assistantsService'
import type { Assistant, AssistantWithGroups } from '../types/assistant.types'

interface GroupOption {
  id_grupo: number
  label: string
}

interface AssignAssistantModalProps {
  open: boolean
  groups: GroupOption[]
  existingAssignments: AssistantWithGroups[]
  /** Recibe el auxiliar y la lista de grupos seleccionados. */
  onConfirm: (userId: string, groupIds: number[]) => Promise<void>
  onCancel: () => void
}

/**
 * Modal "Asignar auxiliar".
 *
 * Permite asignar al mismo auxiliar a VARIOS grupos a la vez.
 * Los grupos donde ya está aparecen marcados y deshabilitados.
 */
export function AssignAssistantModal({
  open,
  groups,
  existingAssignments,
  onConfirm,
  onCancel,
}: AssignAssistantModalProps) {
  const [criterion, setCriterion] = useState('')
  const [isSearching, setIsSearching] = useState(false)
  const [result, setResult] = useState<Assistant | null>(null)
  const [hasSearched, setHasSearched] = useState(false)
  const [selectedGroupIds, setSelectedGroupIds] = useState<number[]>([])
  const [isSubmitting, setIsSubmitting] = useState(false)

  function reset() {
    setCriterion('')
    setResult(null)
    setHasSearched(false)
    setSelectedGroupIds([])
    setIsSearching(false)
    setIsSubmitting(false)
  }

  async function handleSearch(event: FormEvent) {
    event.preventDefault()
    const trimmed = criterion.trim()

    if (trimmed.length < 2) return

    setIsSearching(true)
    setHasSearched(false)
    setResult(null)
    setSelectedGroupIds([])

    try {
      const results = await assistantsService.search(trimmed)
      setResult(results[0] ?? null)
    } catch {
      setResult(null)
    } finally {
      setHasSearched(true)
      setIsSearching(false)
    }
  }

  function toggleGroup(groupId: number) {
    setSelectedGroupIds((prev) =>
      prev.includes(groupId)
        ? prev.filter((id) => id !== groupId)
        : [...prev, groupId]
    )
  }

  async function handleConfirm() {
    if (result === null || selectedGroupIds.length === 0) return

    setIsSubmitting(true)
    try {
      await onConfirm(result.id_usuario, selectedGroupIds)
      reset()
    } finally {
      setIsSubmitting(false)
    }
  }

  function handleClose() {
    reset()
    onCancel()
  }

  const assistantGroups = result
    ? existingAssignments.find((a) => a.id_usuario === result.id_usuario)?.grupos ?? []
    : []

  // Cuántos grupos seleccionados NO están ya asignados (los que se van a agregar)
  const newAssignmentsCount = selectedGroupIds.filter(
    (id) => !assistantGroups.some((g) => g.id_grupo === id)
  ).length

  return (
    <Dialog open={open} onOpenChange={(o) => !o && handleClose()}>
      <DialogContent className="sm:max-w-[560px]">
        <DialogHeader>
          <DialogTitle>Asignar auxiliar</DialogTitle>
        </DialogHeader>

        <form onSubmit={handleSearch}>
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
            <Input
              value={criterion}
              onChange={(e) => setCriterion(e.target.value)}
              placeholder="Nombre, código SIS"
              className="pl-9"
              disabled={isSearching}
            />
          </div>
        </form>

        {result && (
          <>
            <div className="flex items-center gap-3 rounded-md border border-input bg-muted/30 px-3 py-2">
              <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand text-brand-foreground text-xs font-medium">
                {initialsOf(result)}
              </span>
              <div className="min-w-0">
                <p className="text-sm font-medium truncate">{result.nombre_completo}</p>
                <p className="text-xs text-muted-foreground font-mono">
                  {result.cod_sis}
                </p>
              </div>
            </div>

            <div className="space-y-2">
              <Label>Grupos</Label>

              {groups.map((g) => {
                const isAssigned = assistantGroups.some(
                  (ga) => ga.id_grupo === g.id_grupo
                )
                const isSelected = selectedGroupIds.includes(g.id_grupo)

                return (
                  <label
                    key={g.id_grupo}
                    className={`flex items-center gap-2 ${
                      isAssigned ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={isAssigned || isSelected}
                      disabled={isAssigned || isSubmitting}
                      onChange={() => !isAssigned && toggleGroup(g.id_grupo)}
                      className="size-4 rounded border-input"
                    />
                    <span className="text-sm">{g.label}</span>
                    {isAssigned && (
                      <span className="ml-auto text-xs text-destructive">
                        Ya está asignada a este grupo.
                      </span>
                    )}
                  </label>
                )
              })}
            </div>
          </>
        )}

        {hasSearched && result === null && !isSearching && (
          <Alert className="border-info-border bg-info-soft text-info">
            <AlertCircle className="size-4" />
            <AlertTitle>No hay un auxiliar registrado con ese código</AlertTitle>
            <AlertDescription>
              El administrador debe crear la cuenta. Luego podrá asignarlo desde aquí.
            </AlertDescription>
          </Alert>
        )}

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="outline" onClick={handleClose} disabled={isSubmitting}>
            Cancelar
          </Button>
          {result && (
            <Button
              variant="default"
              onClick={handleConfirm}
              disabled={isSubmitting || newAssignmentsCount === 0}
            >
              {isSubmitting
                ? 'Asignando…'
                : newAssignmentsCount === 0
                  ? 'Seleccione al menos un grupo'
                  : `Asignar a ${newAssignmentsCount} ${newAssignmentsCount === 1 ? 'grupo' : 'grupos'}`}
            </Button>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}

function initialsOf(assistant: Assistant): string {
  const n = assistant.nombre?.[0] ?? ''
  const a = assistant.apellido_paterno?.[0] ?? ''
  return (n + a).toUpperCase()
}