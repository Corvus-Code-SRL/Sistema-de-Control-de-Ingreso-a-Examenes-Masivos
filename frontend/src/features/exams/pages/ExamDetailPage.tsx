import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { AppShell } from '@/components/layout/AppShell';
import { EmptyState } from '@/components/common/EmptyState';
import { ErrorState } from '@/components/common/ErrorState';
import { LoadingState } from '@/components/common/LoadingState';
import { PageHeader } from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { AlertTriangle, Ban, Loader2, Save, ShieldAlert } from 'lucide-react';
import { useExamDetail } from '../hooks/useExamDetail';
import { ExamForm } from '../components/ExamForm';
import { AssignGroupsForm } from '../components/AssignGroupsForm';
import { ExamStatusBadge } from '../components/ExamStatusBadge';
import { validateExamForm, validateGroupsStep } from '../utils/examValidators';

/**
 * Detalle de un examen: edición de su información general, gestión de grupos
 * y cancelación mientras está PROGRAMADO (HU-24 criterios 10-12, HU-25
 * criterio 7). Desde EN_INGRESO todo se muestra de solo lectura (criterio 8).
 */
export function ExamDetailPage() {
  const navigate = useNavigate();
  const { examId } = useParams<{ examId: string }>();
  const id = Number(examId);
  const [showCancelDialog, setShowCancelDialog] = useState(false);

  const {
    exam,
    loading,
    notFound,
    forbidden,
    loadError,
    reload,
    formData,
    updateFormData,
    options,
    subjectGroups,
    selectedSubject,
    isProgramado,
    toggleGroup,
    savingInfo,
    infoError,
    infoWarnings,
    saveGeneralInfo,
    savingGroups,
    groupsError,
    saveGroups,
    cancelling,
    cancelError,
    cancelExam,
  } = useExamDetail(id);

  const backTo = '/examenes/programados';

  if (notFound || forbidden) {
    return (
      <AppShell mobileTitle="Examen" breadcrumbs={[{ label: 'Exámenes' }, { label: 'Programados', to: backTo }, { label: 'Detalle' }]}>
        <EmptyState
          icon={ShieldAlert}
          title={notFound ? 'Examen no encontrado' : 'No tiene acceso a este examen'}
          description={
            notFound
              ? 'El examen no existe o ya no está disponible.'
              : 'Solo el docente que lo creó puede ver y modificar este examen.'
          }
          action={
            <Button variant="outline" onClick={() => navigate(backTo)}>
              Volver a Programados
            </Button>
          }
        />
      </AppShell>
    );
  }

  const infoValidation = validateExamForm(formData);
  const groupsValidation = validateGroupsStep(formData, subjectGroups);

  const handleSaveInfo = (e: React.FormEvent) => {
    e.preventDefault();
    if (infoValidation.isValid) saveGeneralInfo();
  };

  const handleConfirmCancel = async () => {
    await cancelExam();
    setShowCancelDialog(false);
  };

  return (
    <AppShell
      mobileTitle={exam?.nombre_examen ?? 'Examen'}
      breadcrumbs={[{ label: 'Exámenes' }, { label: 'Programados', to: backTo }, { label: exam?.nombre_examen ?? 'Detalle' }]}
    >
      <PageHeader
        title={exam?.nombre_examen ?? 'Examen'}
        backTo={backTo}
        backLabel="Volver a Programados"
        subtitle={exam && <ExamStatusBadge status={exam.estado} />}
        actions={
          exam && isProgramado && (
            <Button
              type="button"
              variant="outline"
              onClick={() => setShowCancelDialog(true)}
              className="border-danger/30 text-danger hover:bg-danger-soft"
            >
              <Ban className="size-4" aria-hidden="true" /> Cancelar examen
            </Button>
          )
        }
      />

      {loading && <LoadingState rows={5} label="Cargando el examen" />}

      {!loading && loadError && <ErrorState error={loadError} onRetry={reload} />}

      {!loading && !loadError && exam && (
        <div className="space-y-6">
          {!isProgramado ? (
            <div className="rounded-xl border bg-card p-6 shadow-xs space-y-4">
              <h2 className="sciem-h3">Información general</h2>
              <p className="text-sm text-muted-foreground">
                El control de ingreso de este examen ya se inició: la información general y sus
                grupos quedaron fijos.
              </p>
              <dl className="grid grid-cols-1 gap-x-8 gap-y-3 text-sm md:grid-cols-2">
                <div>
                  <dt className="font-medium">Materia</dt>
                  <dd className="text-muted-foreground">{exam.materia?.nombre} — {exam.carrera?.nombre}</dd>
                </div>
                <div>
                  <dt className="font-medium">Fecha y hora</dt>
                  <dd className="text-muted-foreground">{exam.fecha}, {exam.hora_inicio}–{exam.hora_fin}</dd>
                </div>
                <div>
                  <dt className="font-medium">Duración</dt>
                  <dd className="text-muted-foreground">{exam.duracion} min</dd>
                </div>
                <div>
                  <dt className="font-medium">Ambientes</dt>
                  <dd className="text-muted-foreground">
                    {(exam.ambientes ?? []).map((room) => room.nro_aula).join(', ') || '—'}
                  </dd>
                </div>
              </dl>
            </div>
          ) : (
            <form onSubmit={handleSaveInfo} className="space-y-4">
              {infoError && (
                <div role="alert" className="rounded-xl border border-danger/20 bg-danger-soft p-4 text-sm font-medium text-danger-fg">
                  {infoError}
                </div>
              )}

              <ExamForm
                formData={formData}
                subjects={options.materias}
                classrooms={options.ambientes}
                errors={infoValidation.errors}
                warnings={{}}
                updateFormData={updateFormData}
              />

              {infoWarnings.length > 0 && (
                <div role="alert" className="space-y-3 rounded-xl border border-warn-border bg-warn-soft p-4 text-sm text-warn-fg">
                  <p className="flex items-center gap-2 font-bold">
                    <AlertTriangle className="size-4" aria-hidden="true" /> Revise antes de confirmar
                  </p>
                  <ul className="list-disc space-y-1 pl-5">
                    {infoWarnings.map((warning) => (
                      <li key={warning}>{warning}</li>
                    ))}
                  </ul>
                  <Button type="button" disabled={savingInfo} onClick={() => saveGeneralInfo(true)}>
                    Guardar de todos modos
                  </Button>
                </div>
              )}

              <div className="flex items-center justify-end">
                <Button type="submit" disabled={savingInfo || infoWarnings.length > 0}>
                  {savingInfo ? (
                    <>
                      <Loader2 className="size-4 animate-spin" aria-hidden="true" /> Guardando...
                    </>
                  ) : (
                    <>
                      <Save className="size-4" aria-hidden="true" /> Guardar información general
                    </>
                  )}
                </Button>
              </div>
            </form>
          )}

          {isProgramado ? (
            <div className="space-y-4">
              {groupsError && (
                <div role="alert" className="rounded-xl border border-danger/20 bg-danger-soft p-4 text-sm font-medium text-danger-fg">
                  {groupsError}
                </div>
              )}

              <AssignGroupsForm
                selectedSubject={selectedSubject}
                availableGroups={subjectGroups}
                selectedGroupIds={formData.grupos}
                errors={{}}
                onToggleGroup={toggleGroup}
              />

              <div className="flex items-center justify-end">
                <Button type="button" disabled={savingGroups || !groupsValidation.isValid} onClick={() => saveGroups()}>
                  {savingGroups ? (
                    <>
                      <Loader2 className="size-4 animate-spin" aria-hidden="true" /> Guardando...
                    </>
                  ) : (
                    <>
                      <Save className="size-4" aria-hidden="true" /> Guardar grupos
                    </>
                  )}
                </Button>
              </div>
            </div>
          ) : (
            <div className="rounded-xl border bg-card p-6 shadow-xs space-y-4">
              <h2 className="sciem-h3">Grupos participantes</h2>
              <div className="divide-y overflow-hidden rounded-lg border">
                {(exam.grupos ?? []).map((group) => (
                  <div key={group.id_grupo} className="flex items-center justify-between px-4 py-3.5 text-sm">
                    <span className="font-medium">Grupo {group.num_grupo}</span>
                    <span className="text-muted-foreground">{group.cantidad_estudiantes} estudiantes</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {exam && (
        <Dialog open={showCancelDialog} onOpenChange={setShowCancelDialog}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Cancelar «{exam.nombre_examen}»</DialogTitle>
              <DialogDescription>
                Esta acción cancela el examen y no puede deshacerse desde aquí. El registro queda
                como cancelado, no se elimina.
              </DialogDescription>
            </DialogHeader>
            {cancelError && <p className="text-sm font-medium text-danger">{cancelError}</p>}
            <DialogFooter>
              <Button variant="outline" onClick={() => setShowCancelDialog(false)} disabled={cancelling}>
                Volver
              </Button>
              <Button onClick={handleConfirmCancel} disabled={cancelling} variant="destructive">
                {cancelling ? 'Cancelando...' : 'Sí, cancelar examen'}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      )}
    </AppShell>
  );
}
