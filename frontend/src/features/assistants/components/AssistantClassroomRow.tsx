import { AlertTriangle, DoorOpen, Loader2 } from 'lucide-react'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { ExamAssistant, ExamClassroom } from '../types/assistant.types'

/**
 * El resaltado por defecto del Select usa `accent`, que en SCIEM es el amarillo de la
 * marca. En esta lista basta un gris suave, como un hover.
 */
const ITEM_HIGHLIGHT_CLASS =
  'focus:bg-muted focus:text-foreground not-data-[variant=destructive]:focus:**:text-foreground'

interface AssistantClassroomRowProps {
  assistant: ExamAssistant
  classrooms: ExamClassroom[]
  editable: boolean
  saving: boolean
  disabled: boolean
  onAssign: (assistant: ExamAssistant, classroomId: number) => void
}

/**
 * Un auxiliar habilitado con el ambiente donde controla el ingreso (mockup 9.1 y 9.4).
 *
 * El aviso «Sin ambiente» va bajo el nombre, junto al código SIS: así el nombre no se
 * recorta en móvil y la fila no repite el mismo dato con dos íconos.
 */
export function AssistantClassroomRow({
  assistant,
  classrooms,
  editable,
  saving,
  disabled,
  onAssign,
}: AssistantClassroomRowProps) {
  const hasClassroom = assistant.id_ambiente !== null

  return (
    <li className="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:gap-4">
      <div className="flex min-w-0 flex-1 items-start gap-3 sm:items-center">
        <span
          aria-hidden="true"
          className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-bold text-brand-deep"
        >
          {initials(assistant.nombre_completo)}
        </span>

        <div className="min-w-0 flex-1 space-y-1">
          <p className="text-sm font-medium break-words">{assistant.nombre_completo}</p>

          <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span className="text-xs tracking-wide text-muted-foreground tabular-nums">
              {assistant.cod_sis}
            </span>

            {!hasClassroom && (
              <span className="inline-flex items-center gap-1 rounded-full bg-warn-soft px-2 py-0.5 text-xs font-medium text-warn-fg">
                <AlertTriangle className="size-3" aria-hidden="true" />
                Sin ambiente
              </span>
            )}
          </div>
        </div>
      </div>

      {editable ? (
        <div className="flex w-full items-center gap-2 sm:w-auto">
          <Select
            value={hasClassroom ? String(assistant.id_ambiente) : ''}
            onValueChange={(value) => onAssign(assistant, Number(value))}
            disabled={disabled}
          >
            <SelectTrigger
              aria-label={`Ambiente de ${assistant.nombre_completo}`}
              className="w-full bg-card hover:bg-muted/50 data-[size=default]:h-12 sm:w-60 sm:data-[size=default]:h-10"
            >
              <SelectValue placeholder="Sin asignar" />
            </SelectTrigger>
            <SelectContent position="popper" sideOffset={4}>
              {classrooms.map((classroom) => (
                <SelectItem
                  key={classroom.id_ambiente}
                  value={String(classroom.id_ambiente)}
                  className={ITEM_HIGHLIGHT_CLASS}
                >
                  {classroom.nro_aula}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          {saving && (
            <Loader2
              role="status"
              aria-label="Guardando"
              className="size-4 shrink-0 animate-spin text-muted-foreground"
            />
          )}
        </div>
      ) : (
        assistant.ambiente && (
          <span className="inline-flex items-center gap-1.5 pl-11 text-sm font-medium sm:pl-0">
            <DoorOpen className="size-4 text-muted-foreground" aria-hidden="true" />
            {assistant.ambiente.nro_aula}
          </span>
        )
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
