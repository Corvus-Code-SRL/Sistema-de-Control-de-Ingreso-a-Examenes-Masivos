import { FormSelect } from '@/components/common/FormSelect'
import { Label } from '@/components/ui/label'
import { examOptionLabel } from '../lib/collaboratorFormat'
import type { InvitableExam } from '../types/collaborator.types'

interface InvitableExamSelectProps {
  exams: InvitableExam[]
  /** id_examen elegido, como texto; vacío mientras no se elija ninguno. */
  value: string
  onChange: (examId: string) => void
}

/** Desplegable con los exámenes PROGRAMADOS propios: nombre, materia, fecha, hora y ambientes de cada uno. */
export function InvitableExamSelect({ exams, value, onChange }: InvitableExamSelectProps) {
  return (
    <div className="space-y-2">
      <Label htmlFor="collaborator-exam">Examen</Label>
      <FormSelect
        id="collaborator-exam"
        ariaLabel="Examen"
        value={value}
        placeholder="Seleccione un examen programado"
        options={exams.map((exam) => ({ value: String(exam.id_examen), label: examOptionLabel(exam) }))}
        onValueChange={onChange}
      />
    </div>
  )
}
