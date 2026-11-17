import { useState } from 'react'
import { ClipboardList } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { FormSelect } from '@/components/common/FormSelect'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Card } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { EXCEPTIONAL_MESSAGES } from '../components/exceptionalStudentMessages'
import { ExceptionalStudentWorkspace } from '../components/ExceptionalStudentWorkspace'
import { useExceptionalExams } from '../hooks/useExceptionalExams'
import type { ExceptionalExam } from '../types/exceptional-student.types'

/** «AAAA-MM-DD» a «DD/MM/AAAA» sin pasar por Date, para no depender de la zona horaria. */
function formatDate(date: string): string {
  const [year, month, day] = date.split('-')

  return year && month && day ? `${day}/${month}/${year}` : date
}

function examLabel(exam: ExceptionalExam): string {
  return `${exam.nombre_examen} · ${formatDate(exam.fecha)} ${exam.hora_inicio}`
}

/**
 * Registrar estudiante excepcional (HU-023): acceso independiente desde el menú «Control de ingreso y
 * examen en curso». Elige uno de sus exámenes En curso, busca al estudiante por código SIS y lo agrega
 * a la lista de ese examen con su motivo.
 */
export function ExceptionalStudentPage() {
  const exams = useExceptionalExams()
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [notice, setNotice] = useState('')
  const list = exams.data ?? []
  const selected = list.find((exam) => exam.id_examen === selectedId) ?? null

  return (
    <AppShell
      mobileTitle="Estudiante excepcional"
      breadcrumbs={[{ label: 'Registrar estudiante excepcional' }]}
    >
      <PageHeader
        title="Registrar estudiante excepcional"
        subtitle="Agregue a la lista de uno de sus exámenes En curso a un estudiante que no figura en ella, como una convalidación o una inscripción tardía."
      />

      {notice && (
        <p role="status" className="text-sm text-warn-fg">
          {notice}
        </p>
      )}

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
          <FormSelect
            id="exceptional-exam"
            value={selectedId === null ? '' : String(selectedId)}
            placeholder="Elija un examen"
            options={list.map((exam) => ({ value: String(exam.id_examen), label: examLabel(exam) }))}
            onValueChange={(value) => {
              setNotice('')
              setSelectedId(Number(value))
            }}
            className="sm:w-[32rem]"
          />
        </Card>
      )}

      {selected && (
        <ExceptionalStudentWorkspace
          key={selected.id_examen}
          exam={selected}
          onExamChanged={(message) => {
            setNotice(message)
            exams.reload()
          }}
        />
      )}
    </AppShell>
  )
}
