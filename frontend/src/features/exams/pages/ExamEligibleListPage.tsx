import { useState } from 'react'
import { CalendarX2, Lock, Upload } from 'lucide-react'

import { AppShell } from '@/components/layout/AppShell'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { FormSelect } from '@/components/common/FormSelect'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'

import { useEligibleList } from '../hooks/useEligibleList'
import { EligibleListCurrentTable } from '../components/EligibleListCurrentTable'
import { EligibleListUploadFlow } from '../components/EligibleListUploadFlow'
import { formatDate, readOnlyMessage } from '../utils/eligibleListUtils'

export function ExamEligibleListPage() {
  const eligible = useEligibleList()
  const [uploadOpen, setUploadOpen] = useState(false)

  const examOptions = eligible.exams.map((e) => ({
    value: String(e.id_examen),
    label: `${e.nombre} · ${e.materia.nombre} · ${formatDate(e.fecha)} · ${e.hora_inicio}`,
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

  const handleReplaced = (): void => {
    void eligible.reloadList()
  }

  if (eligible.isLoadingExams) {
    return (
      <AppShell
        mobileTitle="Cargar lista de habilitados"
        breadcrumbs={[
          { label: 'Preparación de exámenes' },
          { label: 'Cargar lista de habilitados' },
        ]}
      >
        <Card className="p-0">
          <LoadingState rows={3} label="Cargando exámenes" />
        </Card>
      </AppShell>
    )
  }

  if (eligible.error) {
    return (
      <AppShell
        mobileTitle="Cargar lista de habilitados"
        breadcrumbs={[
          { label: 'Preparación de exámenes' },
          { label: 'Cargar lista de habilitados' },
        ]}
      >
        <PageHeader
          title="Cargar lista de habilitados"
          subtitle="Seleccione un examen y cargue el archivo con los estudiantes habilitados."
        />
        <Card className="p-0">
          <ErrorState error={eligible.error} onRetry={() => eligible.reloadExams()} />
        </Card>
      </AppShell>
    )
  }

  if (eligible.exams.length === 0) {
    return (
      <AppShell
        mobileTitle="Cargar lista de habilitados"
        breadcrumbs={[
          { label: 'Preparación de exámenes' },
          { label: 'Cargar lista de habilitados' },
        ]}
      >
        <PageHeader
          title="Cargar lista de habilitados"
          subtitle="Seleccione un examen y cargue el archivo con los estudiantes habilitados."
        />
        <Card className="p-0">
          <EmptyState
            icon={CalendarX2}
            title="Aún no tiene exámenes disponibles"
            description="Cuando registre un examen, podrá cargar la lista de habilitados desde aquí."
          />
        </Card>
      </AppShell>
    )
  }

  return (
    <AppShell
      mobileTitle="Cargar lista de habilitados"
      breadcrumbs={[
        { label: 'Preparación de exámenes' },
        { label: 'Cargar lista de habilitados' },
      ]}
    >
      <PageHeader
        title="Cargar lista de habilitados"
        subtitle="Cargue el archivo con los estudiantes habilitados: quienes no figuren en él quedarán como no habilitados."
      />

      {/* #1: selector + grupos en una sola card row */}
      <Card className="p-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
          <div className="w-full max-w-md space-y-1">
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
            />
          </div>
          {selectedExam && (
            <span className="text-xs font-medium text-muted-foreground sm:pb-2">
              Grupos{' '}
              {selectedExam.grupos && selectedExam.grupos.length > 0
                ? selectedExam.grupos.join(' y ')
                : '—'}
              {selectedExam.materia && ` · ${selectedExam.materia.nombre}`}
            </span>
          )}
        </div>
      </Card>

      <Card className="p-4">
        <p className="sciem-label">Formato del archivo</p>
        <p className="sciem-supporting mt-1 text-muted-foreground">
          Columnas: Estudiante (código SIS), Apellidos, Nombres y, opcional, Carrera. Se compara por
          código SIS; los demás datos son referenciales. Formatos: CSV o XLSX
        </p>
        <a
          href="/plantillas/nomina-plantilla.csv"
          download="nomina-plantilla.csv"
          className="mt-3 inline-flex items-center text-sm font-medium text-brand underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          Descargar plantilla
        </a>
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
          {!eligible.listData.editable &&
            (() => {
              const msg = readOnlyMessage(eligible.listData.estado)
              return (
                <Alert className="border-info-border bg-info-soft text-info">
                  <Lock aria-hidden="true" />
                  <AlertTitle>{msg.title}</AlertTitle>
                  <AlertDescription>{msg.description}</AlertDescription>
                </Alert>
              )
            })()}

          <EligibleListCurrentTable
            students={students}
            toolbar={
              <Button
                variant="outline"
                onClick={() => setUploadOpen(true)}
                disabled={!eligible.listData.editable}
              >
                <Upload aria-hidden="true" className="size-4" />
                Importar desde archivo
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

    </AppShell>
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
