import { useState } from 'react'
import { CalendarX2, Lock, Upload } from 'lucide-react'

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { FormSelect } from '@/components/common/FormSelect'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'

import { useEligibleList } from '../hooks/useEligibleList'
import { EligibleListCurrentTable } from '../components/EligibleListCurrentTable'
import { EligibleListShell, ELIGIBLE_LIST_TITLE } from '../components/EligibleListShell'
import { EligibleListUploadFlow } from '../components/EligibleListUploadFlow'
import { formatDate, formatTime, readOnlyMessage } from '../utils/eligibleListUtils'

const SELECT_SUBTITLE = 'Seleccione un examen y cargue el archivo con los estudiantes habilitados.'
const REPLACE_SUBTITLE =
  'Cargue el archivo con los estudiantes habilitados: quienes no figuren en él quedarán como no habilitados.'

export function ExamEligibleListPage() {
  const eligible = useEligibleList()
  const [uploadOpen, setUploadOpen] = useState(false)

  const examOptions = eligible.exams.map((e) => ({
    value: String(e.id_examen),
    label: `${e.nombre} · ${e.materia.nombre} · ${formatDate(e.fecha)} · ${formatTime(e.hora_inicio)}`,
  }))

  const selectedExam = eligible.selectedExam

  const totalNomina = eligible.listData?.estudiantes.length ?? 0
  const habilitadosCount =
    eligible.listData?.estudiantes.filter(
      (s) =>
        s.estado_habilitacion === 'HABILITADO' ||
        s.estado_habilitacion === 'CASO_ESPECIAL'
    ).length ?? 0
  const noHabilitadosCount =
    eligible.listData?.estudiantes.filter(
      (s) => s.estado_habilitacion === 'NO_HABILITADO'
    ).length ?? 0

  const students = eligible.listData?.estudiantes ?? []

  // El archivo reemplaza la lista actual. El servidor lo hace en una sola transacción; aquí solo se recarga.
  const replacesCurrentList = noHabilitadosCount > 0

  const readOnly =
    eligible.listData && !eligible.listData.editable ? readOnlyMessage(eligible.listData.estado) : null

  const handleReplaced = (): void => {
    void eligible.reloadList()
  }

  if (eligible.isLoadingExams) {
    return (
      <EligibleListShell>
        <Card className="p-0">
          <LoadingState rows={3} label="Cargando exámenes" />
        </Card>
      </EligibleListShell>
    )
  }

  if (eligible.error) {
    return (
      <EligibleListShell subtitle={SELECT_SUBTITLE}>
        <Card className="p-0">
          <ErrorState error={eligible.error} onRetry={() => eligible.reloadExams()} />
        </Card>
      </EligibleListShell>
    )
  }

  if (eligible.exams.length === 0) {
    return (
      <EligibleListShell subtitle={SELECT_SUBTITLE}>
        <Card className="p-0">
          <EmptyState
            icon={CalendarX2}
            title="Aún no tiene exámenes disponibles"
            description="Cuando registre un examen, podrá cargar la lista de habilitados desde aquí."
          />
        </Card>
      </EligibleListShell>
    )
  }

  return (
    <EligibleListShell subtitle={REPLACE_SUBTITLE}>
      <Card className="p-4">
        <div className="w-full space-y-2 sm:max-w-xl">
          <label htmlFor="exam-selector" className="sciem-label">
            Examen
          </label>
          <FormSelect
            id="exam-selector"
            value={eligible.selectedExamId ? String(eligible.selectedExamId) : ''}
            placeholder="Seleccione un examen"
            options={examOptions}
            onValueChange={(v) => eligible.selectExam(Number(v))}
            ariaLabel="Examen"
            className="h-12 text-base"
          />
        </div>

        {selectedExam && (
          <dl
            aria-label="Contexto del examen"
            className="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 border-t border-border-soft pt-4 sm:grid-cols-2 lg:grid-cols-3"
          >
            <ContextItem label="Materia" value={selectedExam.materia?.nombre ?? '—'} />
            <ContextItem
              label="Grupos"
              value={selectedExam.grupos?.length ? selectedExam.grupos.join(' y ') : '—'}
            />
            <ContextItem label="Fecha" value={formatDate(selectedExam.fecha)} />
            <ContextItem label="Hora de inicio" value={formatTime(selectedExam.hora_inicio)} />
            <ContextItem
              label="Ambientes"
              value={
                selectedExam.ambientes?.length
                  ? selectedExam.ambientes.map((room) => room.nro_aula).join(', ')
                  : 'Sin ambientes'
              }
            />
          </dl>
        )}
      </Card>

      {eligible.listData && (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <SummaryCard label="En la nómina del examen" value={totalNomina} />
          <SummaryCard label="Habilitados" value={habilitadosCount} tone="ok" />
          <SummaryCard label="No habilitados" value={noHabilitadosCount} tone="danger" />
        </div>
      )}

      {eligible.isLoadingList && (
        <Card className="p-0">
          <LoadingState rows={4} label="Cargando lista de habilitados" />
        </Card>
      )}

      {!eligible.isLoadingList && eligible.listData && (
        <>
          {readOnly && (
            <Alert className="border-info-border bg-info-soft text-info">
              <Lock aria-hidden="true" />
              <AlertTitle>{readOnly.title}</AlertTitle>
              <AlertDescription>{readOnly.description}</AlertDescription>
            </Alert>
          )}

          <EligibleListCurrentTable
            students={students}
            toolbar={
              <Button
                variant="outline"
                onClick={() => setUploadOpen(true)}
                disabled={!eligible.listData.editable}
              >
                <Upload aria-hidden="true" className="size-4" />
                {ELIGIBLE_LIST_TITLE}
              </Button>
            }
          />
        </>
      )}

      <EligibleListUploadFlow
        open={uploadOpen}
        examId={eligible.selectedExamId}
        replacesCurrentList={replacesCurrentList}
        onClose={() => setUploadOpen(false)}
        onReplaced={handleReplaced}
      />
    </EligibleListShell>
  )
}

function ContextItem({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-xs font-medium text-muted-foreground">{label}</dt>
      <dd className="sciem-tnum text-sm font-medium text-foreground">{value}</dd>
    </div>
  )
}

function SummaryCard({
  label,
  value,
  tone,
}: {
  label: string
  value: number
  tone?: 'ok' | 'danger'
}) {
  const cls =
    tone === 'ok'
      ? 'bg-ok-soft text-ok-fg border-ok-border'
      : tone === 'danger'
        ? 'bg-danger-soft text-danger-fg border-danger-border'
        : 'bg-sunken'
  return (
    <div className={`rounded-xl border border-border-soft p-4 ${cls}`}>
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <p className="sciem-tnum text-2xl font-semibold">{value}</p>
    </div>
  )
}
