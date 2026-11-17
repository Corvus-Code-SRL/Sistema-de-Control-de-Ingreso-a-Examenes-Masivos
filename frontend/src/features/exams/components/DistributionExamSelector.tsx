import { FormSelect } from '@/components/common/FormSelect'
import { Card } from '@/components/ui/card'
import type { DistributionExamOption } from '../types/roomDistribution.types'
import { formatDate } from '../utils/eligibleListUtils'

interface Props {
  exams: DistributionExamOption[]
  selectedId: number | null
  disabled: boolean
  onSelect: (id: number) => void
}

export function DistributionExamSelector({ exams, selectedId, disabled, onSelect }: Props) {
  return (
    <Card className="p-4">
      <div className="w-full max-w-2xl space-y-2">
        <label htmlFor="distribution-exam" className="sciem-label">Examen Programado</label>
        <FormSelect id="distribution-exam" ariaLabel="Examen Programado"
          value={selectedId === null ? '' : String(selectedId)} placeholder="Seleccione un examen"
          disabled={disabled} onValueChange={(value) => onSelect(Number(value))}
          options={exams.map((exam) => ({
            value: String(exam.id_examen),
            label: `${exam.nombre} · ${exam.materia?.nombre ?? 'Sin materia'} · ${formatDate(exam.fecha)} · ${exam.hora_inicio.slice(0, 5)}`,
          }))}
        />
      </div>
    </Card>
  )
}
