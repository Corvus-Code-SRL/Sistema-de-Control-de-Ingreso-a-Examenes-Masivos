import { useCallback, useState, type ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { FormSelect } from '@/components/common/FormSelect'
import { LoadingState } from '@/components/common/LoadingState'
import { Card } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import type { SituationExam, SituationExamsPage } from '../types/exam-situation.types'
import { ExamPagination } from './ExamPagination'

interface Props {
  fetchExams: (page: number, signal?: AbortSignal) => Promise<SituationExamsPage>
  selectId: string
  icon: LucideIcon
  emptyTitle: string
  emptyDescription: string
  stateLabels: Record<SituationExam['estado'], string>
  /** Con un único examen elegible lo selecciona sin pedir el paso. */
  autoSelectSingle?: boolean
  children: (examId: number) => ReactNode
}

/** Selector paginado de examen con estado vacío; lo comparten los accesos de HU-057 y HU-042. */
export function ExamSelection(props: Props) {
  const [page, setPage] = useState(1)
  const [examId, setExamId] = useState('')
  return <ExamSelectionPage key={page} {...props} page={page} examId={examId}
    onSelect={setExamId} onPage={(value) => { setExamId(''); setPage(value) }} />
}

interface PageProps extends Props {
  page: number
  examId: string
  onSelect: (value: string) => void
  onPage: (value: number) => void
}

function ExamSelectionPage({
  fetchExams, selectId, icon, emptyTitle, emptyDescription, stateLabels, autoSelectSingle,
  children, page, examId, onSelect, onPage,
}: PageProps) {
  const resource = useAsyncResource(useCallback(
    (signal) => fetchExams(page, signal), [fetchExams, page]
  ), [page])
  if (resource.status === 'error' && resource.error) return <ErrorState error={resource.error} onRetry={resource.reload} />
  if (!resource.data) return <LoadingState rows={2} label="Cargando exámenes" />
  const catalog = resource.data
  if (!catalog.data.length && page <= 1) {
    return <EmptyState icon={icon} title={emptyTitle} description={emptyDescription} />
  }
  const selected = examId || (autoSelectSingle && catalog.data.length === 1 ? String(catalog.data[0].id_examen) : '')
  return <>
    <Card className="space-y-3 p-4">
      <Label htmlFor={selectId}>Examen</Label>
      {catalog.data.length ? <FormSelect id={selectId} value={selected} placeholder="Seleccione un examen"
        onValueChange={onSelect} options={catalog.data.map((exam) => ({
          value: String(exam.id_examen),
          label: `${exam.nombre_examen} · ${exam.fecha} · ${stateLabels[exam.estado]} · #${exam.id_examen}`,
        }))} /> : <p role="status">No hay más exámenes en esta página.</p>}
      <ExamPagination page={catalog.meta.current_page} lastPage={catalog.meta.last_page} onChange={onPage} label="exámenes" />
    </Card>
    {selected && children(Number(selected))}
  </>
}
