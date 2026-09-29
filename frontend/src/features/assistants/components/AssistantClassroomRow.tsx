import { AlertTriangle, DoorOpen, Loader2 } from 'lucide-react'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { ExamAssistant, ExamClassroom } from '../types/assistant.types'

interface AssistantClassroomRowProps {
  assistant: ExamAssistant
  classrooms: ExamClassroom[]
  editable: boolean
  saving: boolean
  disabled: boolean
  onAssign: (assistant: ExamAssistant, classroomId: number) => void
}

/** Un auxiliar habilitado con el ambiente donde controla el ingreso (mockup 9.1 y 9.4). */
export function AssistantClassroomRow({
  assistant,
  classrooms,
  editable,
  saving,
  disabled,
  onAssign,
}: AssistantClassroomRowProps) {
  return (
    <li className="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:gap-4">
      <div className="flex min-w-0 flex-1 items-center gap-3">
        <span
          aria-hidden="true"
          className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-bold text-brand-deep"
        >
          {initials(assistant.nombre_completo)}
        </span>

        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-medium">{assistant.nombre_completo}</p>
          <p className="text-xs tracking-wide text-muted-foreground tabular-nums">{assistant.cod_sis}</p>
        </div>

        {assistant.id_ambiente === null && (
          <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-warn-soft px-2 py-0.5 text-xs font-medium text-warn-fg">
            <AlertTriangle className="size-3" aria-hidden="true" />
            Sin ambiente
          </span>
        )}
      </div>

      {editable ? (
        <div className="flex w-full items-center gap-2 sm:w-auto">
          <Select
            value={assistant.id_ambiente === null ? '' : String(assistant.id_ambiente)}
            onValueChange={(value) => onAssign(assistant, Number(value))}
            disabled={disabled}
          >
            <SelectTrigger
              aria-label={`Ambiente de ${assistant.nombre_completo}`}
              className="w-full bg-card data-[size=default]:h-12 sm:w-60 sm:data-[size=default]:h-10"
            >
              <SelectValue placeholder="Sin asignar" />
            </SelectTrigger>
            <SelectContent>
              {classrooms.map((classroom) => (
                <SelectItem key={classroom.id_ambiente} value={String(classroom.id_ambiente)}>
                  {classroom.nro_aula}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          {saving && (
            <Loader2 className="size-4 shrink-0 animate-spin text-muted-foreground" aria-label="Guardando" />
          )}
        </div>
      ) : (
        <span className="inline-flex items-center gap-1.5 text-sm font-medium">
          <DoorOpen className="size-4 text-muted-foreground" aria-hidden="true" />
          {assistant.ambiente?.nro_aula ?? 'Sin asignar'}
        </span>
      )}
    </li>
  )
}

/** Iniciales del avatar: primera letra del nombre y del primer apellido. */
function initials(fullName: string): string {
  return fullName
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
}