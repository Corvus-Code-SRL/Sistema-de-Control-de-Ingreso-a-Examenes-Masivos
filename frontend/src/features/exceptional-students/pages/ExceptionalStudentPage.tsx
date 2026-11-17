import { useState } from 'react'
import { ClipboardList } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Card } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { EXCEPTIONAL_MESSAGES } from '../components/exceptionalStudentMessages'
import { ExceptionalStudentWorkspace } from '../components/ExceptionalStudentWorkspace'
import { useExceptionalExams } from '../hooks/useExceptionalExams'
import type { ExceptionalExam } from '../types/exceptional-student.types'

const STATE_LABELS: Record<string, string> = {
  PROGRAMADO: 'Programado',
  EN_CURSO: 'En curso'
}

/** «AAAA-MM-DD» a «DD/MM/AAAA» sin pasar por Date, para no depender de la zona horaria. */
function formatDate(date: string): string {
  const [year, month, day] = date.split('-')

  return year && month && day ? `${day}/${month}/${year}` : date
}

function examLabel(exam: ExceptionalExam): string {
  const state = STATE_LABELS[exam.estado] ?? exam.estado

  return `${exam.nombre_examen} · ${formatDate(exam.fecha)} ${exam.hora_inicio} · ${state}`
}

/**
 * Registrar estudiante excepcional (HU-023): acceso independiente desde el menú. Elige uno de sus
 * exámenes, busca al estudiante por código SIS y lo agrega a la lista de ese examen con su motivo.
 */
export function ExceptionalStudentPage() {
  const exams = useExceptionalExams()
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const list = exams.data ?? []
  const selected = list.find((exam) => exam.id_examen === selectedId) ?? null

  return (
    <AppShell
      mobileTitle="Estudiante excepcional"
      breadcrumbs={[{ label: 'Registrar estudiante excepcional' }]}
    >
      <PageHeader
        title="Registrar estudiante excepcional"
        subtitle="Agregue a la lista de uno de sus exámenes a un estudiante que no figura en ella, como una convalidación o una inscripción tardía."
      />

      {exams.error && <ErrorState error={exams.error} onRetry={exams.reload} />}

      {!exams.error && exams.data === null && (
        <LoadingState rows={3} label="Cargando sus exámenes" />
      )}

      {exams.data && list.length === 0 && (
        <Card>
          <EmptyState
            icon={ClipboardList}
            title={EXCEPTIONAL_MESSAGES.emptyTitle}
            description={EXCEPTIONAL_MESSAGES.emptyDescription}
          />
        </Card>
      )}

      {list.length > 0 && (
        <Card className="space-y-2 p-4">
          <Label htmlFor="exceptional-exam">Examen</Label>
          <Select
            value={selectedId === null ? '' : String(selectedId)}
            onValueChange={(value) => setSelectedId(Number(value))}
          >
            <SelectTrigger id="exceptional-exam" className="h-11 w-full sm:w-[32rem]">
              <SelectValue placeholder="Elija un examen" />
            </SelectTrigger>
            <SelectContent>
              {list.map((exam) => (
                <SelectItem key={exam.id_examen} value={String(exam.id_examen)}>
                  {examLabel(exam)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </Card>
      )}

      {selected && <ExceptionalStudentWorkspace key={selected.id_examen} exam={selected} />}
    </AppShell>
  )
}
