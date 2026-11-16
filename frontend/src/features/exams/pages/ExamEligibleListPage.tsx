import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  CalendarPlus,
  CalendarX2,
  CheckCircle2,
  Info,
  Lock,
  Upload,
  XCircle,
} from 'lucide-react'

import { AppShell } from '@/components/layout/AppShell'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { FormSelect } from '@/components/common/FormSelect'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Toast } from '@/components/ui/toast'

import { useEligibleList } from '../hooks/useEligibleList'
import { ChangesBanner } from '../components/ChangesBanner'
import { DisableStudentsDialog } from '../components/DisableStudentsDialog'
import { EligibleListCurrentTable } from '../components/EligibleListCurrentTable'
import { EligibleListUploadFlow } from '../components/EligibleListUploadFlow'
import type { NoEligibleReason } from '../types/eligibleList.types'
import { formatDate, readOnlyMessage } from '../utils/eligibleListUtils'

interface ToastState {
  title: string
  description: string
}

export function ExamEligibleListPage() {
  const eligible = useEligibleList()
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [disableOpen, setDisableOpen] = useState(false)
  const [uploadOpen, setUploadOpen] = useState(false)
  const [toast, setToast] = useState<ToastState | null>(null)

  const resetSelection = useCallback(() => setSelectedIds(new Set()), [])

  useEffect(() => {
    resetSelection()
  }, [eligible.selectedExamId, resetSelection])

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

  const students = eligible.studentsWithPending
  const selectedStudents = students.filter((s) => selectedIds.has(s.id_estudiante))
  const selectedNonSpecial = selectedStudents.filter((s) => !s.caso_especial)
  const selectedNoEligible = selectedNonSpecial.filter(
    (s) => s.estado_habilitacion === 'NO_HABILITADO'
  )
  const selectedEligible = selectedNonSpecial.filter(
    (s) => s.estado_habilitacion === 'HABILITADO'
  )

  // Un estudiante es "habilitable" si está seleccionado, no es caso especial,
  // y su estado pendiente (o actual, si no hay pending) no es ya HABILITADO.
  // - Sin pending y estado HABILITADO → ya está habilitado, no ofrecer de nuevo.
  // - Sin pending y estado NO_HABILITADO → ofrecer "Habilitar".
  // - Con pending HABILITADO → ya está pendiente, no ofrecer de nuevo.
  // - Con pending NO_HABILITADO → ofrecer "Habilitar" (para revertir).
  const selectedHabilitable = selectedNonSpecial.filter((s) => {
    const pending = eligible.pendingChanges.get(s.id_estudiante)
    const effectiveEstado = pending?.estado_habilitacion ?? s.estado_habilitacion
    return effectiveEstado !== 'HABILITADO'
  })

  const handleSave = async (): Promise<void> => {
    const count = eligible.pendingChanges.size
    const ok = await eligible.saveChanges()
    if (ok) {
      setToast({
        title: 'Lista de habilitados guardada',
        description: `${count} ${
          count === 1 ? 'cambio aplicado' : 'cambios aplicados'
        } correctamente.`,
      })
    }
  }

  const handleDiscard = (): void => {
    eligible.discardChanges()
    resetSelection()
  }

  const handleApplyDisable = (motivo: NoEligibleReason, detalle?: string): void => {
    eligible.applyDisable(
      selectedNonSpecial.map((s) => s.id_estudiante),
      motivo,
      detalle
    )
    setDisableOpen(false)
    resetSelection()
  }

  const handleApplyEnable = (): void => {
    eligible.applyEnable(selectedHabilitable.map((s) => s.id_estudiante))
    resetSelection()
  }

  if (eligible.isLoadingExams) {
    return (
      <AppShell
        mobileTitle="Habilitar estudiantes"
        breadcrumbs={[
          { label: 'Preparación de exámenes' },
          { label: 'Habilitar estudiantes' },
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
        mobileTitle="Habilitar estudiantes"
        breadcrumbs={[
          { label: 'Preparación de exámenes' },
          { label: 'Habilitar estudiantes' },
        ]}
      >
        <PageHeader
          title="Habilitar estudiantes"
          subtitle="Seleccione un examen para gestionar la lista de habilitados."
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
        mobileTitle="Habilitar estudiantes"
        breadcrumbs={[
          { label: 'Preparación de exámenes' },
          { label: 'Habilitar estudiantes' },
        ]}
      >
        <PageHeader
          title="Habilitar estudiantes"
          subtitle="Seleccione un examen para gestionar la lista de habilitados."
        />
        <Card className="p-0">
          <EmptyState
            icon={CalendarX2}
            title="Aún no tiene exámenes disponibles"
            description="Registre un examen para poder habilitar a los estudiantes de sus grupos."
            action={
              <Button asChild variant="default">
                <Link to="/examenes/nuevo">
                  <CalendarPlus aria-hidden="true" />
                  Ir a registrar examen
                </Link>
              </Button>
            }
          />
        </Card>
      </AppShell>
    )
  }

  return (
    <AppShell
      mobileTitle="Habilitar estudiantes"
      breadcrumbs={[
        { label: 'Preparación de exámenes' },
        { label: 'Habilitar estudiantes' },
      ]}
    >
      <PageHeader
        title="Habilitar estudiantes"
        subtitle="Por defecto todos los estudiantes de los grupos del examen están habilitados. Marque a quienes no cumplen un requisito."
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

      {/* #2: aviso "todos habilitados" arriba de las 3 cards */}
      {eligible.listData &&
        eligible.listData.editable &&
        eligible.listData.estudiantes.length > 0 &&
        eligible.listData.estudiantes.every(
          (s) => s.estado_habilitacion === 'HABILITADO'
        ) && (
          <Alert className="border-ok-border bg-ok-soft text-ok-fg">
            <Info aria-hidden="true" />
            <AlertTitle>Todos los estudiantes están habilitados</AlertTitle>
            <AlertDescription>
              Si nadie tiene que quedar fuera, no necesita hacer nada.
            </AlertDescription>
          </Alert>
        )}

      {/* #4: 3 cards de resumen */}
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

          <ChangesBanner
            count={eligible.pendingChanges.size}
            isSaving={eligible.isSaving}
            onSave={() => void handleSave()}
            onDiscard={handleDiscard}
          />

          {/* #4 (cont.): card de selección ARRIBA de la tabla,
              con los dos botones a la derecha */}
          {selectedIds.size > 0 && eligible.listData.editable && (
            <div className="flex flex-col gap-2 rounded-lg border border-border-soft bg-brand-soft px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
              <span className="text-sm font-medium text-brand-deep">
                {selectedIds.size} seleccionado{selectedIds.size === 1 ? '' : 's'}
              </span>
              <div className="flex flex-wrap items-center gap-2">
                {selectedHabilitable.length > 0 && (
                  <Button size="sm" variant="outline" onClick={handleApplyEnable}>
                    <CheckCircle2 aria-hidden="true" className="size-4" />
                    Habilitar
                  </Button>
                )}
                {selectedNonSpecial.length > 0 && (
                  <Button
                    size="sm"
                    variant="destructive"
                    onClick={() => setDisableOpen(true)}
                  >
                    <XCircle aria-hidden="true" className="size-4" />
                    No habilitar…
                  </Button>
                )}
              </div>
            </div>
          )}

          <EligibleListCurrentTable
            students={students}
            selectedIds={selectedIds}
            onSelectionChange={setSelectedIds}
            editable={eligible.listData.editable}
            hasPendingChange={(id) => eligible.pendingChanges.has(id)}
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

          {selectedNoEligible.length > 0 &&
            selectedHabilitable.length === 0 &&
            selectedNonSpecial.length === selectedNoEligible.length && (
              <p className="text-xs text-muted-foreground">
                Los {selectedNoEligible.length} seleccionados ya están no habilitados.
                Puede habilitarlos o cambiar el motivo.
              </p>
            )}
        </>
      )}

      <DisableStudentsDialog
        open={disableOpen}
        studentCount={selectedNonSpecial.length}
        onClose={() => setDisableOpen(false)}
        onApply={handleApplyDisable}
      />

      <EligibleListUploadFlow
        open={uploadOpen}
        examId={eligible.selectedExamId}
        onClose={() => setUploadOpen(false)}
        onPendingChanges={eligible.mergePendingChanges}
      />

      {toast && (
        <Toast
          title={toast.title}
          description={toast.description}
          onClose={() => setToast(null)}
        />
      )}
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
