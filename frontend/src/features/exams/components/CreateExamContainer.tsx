import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useCreateExam } from '../hooks/useCreateExam';
import { ExamForm } from './ExamForm';
import { ExamAmbientesForm } from './ExamAmbientesForm';
import { AssignGroupsForm } from './AssignGroupsForm';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/common/PageHeader';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { AlertTriangle, Loader2, Save } from 'lucide-react';
import { Exam } from '../types/exams.types';
import { examDetailPath } from '../utils/paths';
import { groupExamsPath } from '@/features/groups';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Toast } from '@/components/ui/toast';

function formatExamToastDate(value: string): string {
  const date = new Date(`${value}T00:00:00`);
  const weekday = new Intl.DateTimeFormat('es-BO', { weekday: 'short' }).format(date).replace('.', '');
  return `${weekday} ${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}`;
}

export const CreateExamContainer: React.FC<{ initialGroupId?: number }> = ({ initialGroupId }) => {
  const navigate = useNavigate();
  const [createdExam, setCreatedExam] = useState<Exam | null>(null);
  const [showSuccessToast, setShowSuccessToast] = useState(false);
  const [showValidationErrors, setShowValidationErrors] = useState(false);

  const {
    formData,
    contextError,
    options,
    subjectGroups,
    totalStudents,
    loading,
    submitting,
    apiError,
    warnings,
    updateFormData,
    resetFormData,
    dismissWarnings,
    validateForm,
    toggleGroup,
    submitExam,
  } = useCreateExam((exam) => {
    setCreatedExam(exam);
    setShowSuccessToast(true);
  }, initialGroupId);

  const cancelTo = initialGroupId === undefined ? '/examenes/programados' : groupExamsPath(initialGroupId);
  const detailGroupId = initialGroupId !== undefined && formData.grupos.includes(initialGroupId)
    ? initialGroupId
    : undefined;
  const validation = validateForm();
  const selectedSubject = options.materias.find(
    (subject) => subject.id_materia === formData.materia?.id_materia
  );

  const totalCapacity = options.ambientes
    .filter((room) => formData.ambientes.includes(room.id_ambiente))
    .reduce((total, room) => total + Number(room.capacidad), 0);
  const hasSelectedRooms = formData.ambientes.length > 0;
  const hasSelectedGroups = formData.grupos.length > 0;
  const capacityDeficit = hasSelectedRooms && hasSelectedGroups
    ? Math.max(0, totalStudents - totalCapacity)
    : 0;
  const occupancyPercent = totalCapacity > 0 && hasSelectedGroups
    ? Math.min(100, Math.round((totalStudents / totalCapacity) * 100))
    : 0;

  const openingMinutes = formData.minutos_apertura === undefined ? 10 : formData.minutos_apertura;
  const [startHour, startMinute] = formData.hora_inicio.split(':').map(Number);
  const opening = formData.control_ingreso !== false && /^\d{4}-\d{2}-\d{2}$/.test(formData.fecha)
    && Number.isInteger(startHour) && Number.isInteger(startMinute)
    && typeof openingMinutes === 'number' && Number.isInteger(openingMinutes)
    && openingMinutes >= 0
    ? (() => {
        const total = startHour * 60 + startMinute - openingMinutes;
        const minutesOfDay = ((total % 1440) + 1440) % 1440;
        const clock = `${String(Math.floor(minutesOfDay / 60)).padStart(2, '0')}:${String(minutesOfDay % 60).padStart(2, '0')}`;
        const date = new Date(`${formData.fecha}T00:00:00Z`);
        date.setUTCDate(date.getUTCDate() + Math.floor(total / 1440));
        return { time: clock, date: date.toISOString().slice(0, 10).split('-').reverse().join('/') };
      })()
    : null;

  const openingText = opening
    ? `${opening.date === createdExam?.fecha.split('-').reverse().join('/') ? '' : `el ${opening.date} `}a las ${opening.time}`
    : 'según lo programado';
  const toastDescription = createdExam
    ? `${[createdExam.nombre_examen, selectedSubject?.nombre, formatExamToastDate(createdExam.fecha), createdExam.hora_inicio.slice(0, 5)].filter(Boolean).join(' · ')}. ${formData.control_ingreso === false ? 'No se realizará control de ingreso.' : `El control se abrirá ${openingText}.`}`
    : '';

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[350px] gap-3 text-muted-foreground">
        <Loader2 className="h-7 w-7 animate-spin text-primary" />
        <p className="text-xs font-medium">Cargando formulario y catálogo de materias...</p>
      </div>
    );
  }

  if (contextError) {
    return (
      <div className="space-y-4">
        <Alert variant="destructive"><AlertDescription>{contextError}</AlertDescription></Alert>
        <Button asChild variant="outline"><Link to={cancelTo}>Volver a los exámenes del grupo</Link></Button>
      </div>
    );
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (submitting || createdExam || warnings.length > 0) {
      return;
    }

    // Mostrar errores únicamente después de intentar guardar.
    setShowValidationErrors(true);

    if (!validation.isValid) {
      return;
    }

    void submitExam();
  };

  return (
    <div className="mx-auto max-w-6xl space-y-4">
      <PageHeader title="Registrar examen" />

      {createdExam && showSuccessToast && (
        <div className="[&>div]:!top-auto [&>div]:bottom-4 [&>div]:border-l-4 [&>div]:border-l-ok sm:[&>div]:bottom-6 sm:[&>div]:!w-[450px]">
          <Toast
            title="Examen registrado · Programado"
            description={toastDescription}
            onClose={() => setShowSuccessToast(false)}
          />
        </div>
      )}

      {apiError && (
        <div role="alert" className="rounded-xl border border-danger-border bg-danger-soft p-4 text-sm font-medium text-danger-fg">
          {apiError}
        </div>
      )}

      <form onSubmit={handleSubmit} className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
        <div className="space-y-3">
          <fieldset disabled={createdExam !== null} className="space-y-3">
            <ExamForm
              formData={formData}
              subjects={options.materias}
              errors={showValidationErrors ? validation.errors : {}}
              updateFormData={updateFormData}
            />

            <AssignGroupsForm
              selectedSubject={selectedSubject}
              availableGroups={subjectGroups}
              subjects={options.materias}
              selectedGroupIds={formData.grupos}
              errors={showValidationErrors ? validation.errors : {}}
              onToggleGroup={toggleGroup}
            />

            <ExamAmbientesForm
              classrooms={options.ambientes}
              selectedRoomIds={formData.ambientes}
              error={showValidationErrors ? validation.errors.ambientes : undefined}
              updateFormData={updateFormData}
            />
          </fieldset>

          <div className="flex flex-wrap items-center justify-between gap-3 pt-1">
            {createdExam ? (
              <>
                <Button asChild variant="outline"><Link to={cancelTo}>
                  {initialGroupId === undefined ? 'Ver en Programados' : 'Volver a exámenes del grupo'}
                </Link></Button>
                <div className="flex flex-wrap gap-2">
                  <Button type="button" variant="outline" onClick={() => {
                    resetFormData();
                    setShowValidationErrors(false);
                    setCreatedExam(null);
                    setShowSuccessToast(false);
                  }}>
                    Crear otro examen
                  </Button>
                  <Button asChild><Link to={examDetailPath(createdExam.id_examen, detailGroupId)}>
                    Ver detalle del examen
                  </Link></Button>
                </div>
              </>
            ) : (
              <>
                <Button type="button" variant="outline" onClick={() => navigate(cancelTo)} disabled={submitting}>
                  Cancelar
                </Button>
                <Button type="submit" disabled={submitting || warnings.length > 0}>
                  {submitting ? (
                    <><Loader2 className="size-4 animate-spin" /> Guardando...</>
                  ) : (
                    <><Save className="size-4" /> Guardar examen</>
                  )}
                </Button>
              </>
            )}
          </div>
        </div>

        <section aria-label="Resumen de capacidad del examen" className="rounded-xl border border-border bg-card p-4 shadow-xs lg:sticky lg:top-16">
          <h2 className="text-base font-semibold text-foreground">Resumen</h2>
          <div className="mt-3 space-y-3">
            <div>
              <h3 className="text-sm font-medium text-muted-foreground">{formData.control_ingreso === false ? 'Control de ingreso' : 'Apertura del control de ingreso'}</h3>
              <p className="mt-1 text-base font-semibold text-foreground">{formData.control_ingreso === false ? 'No se realizará' : opening?.time ?? 'Sin definir'}</p>
              {formData.control_ingreso !== false && opening && <p className="text-sm text-muted-foreground">{opening.date}</p>}
            </div>
            <div className="border-t border-border pt-3">
              <h3 className="text-sm font-medium text-muted-foreground">Capacidad</h3>
              <p className="mt-1 text-sm text-foreground"><strong>{totalStudents ?? 0}</strong> estudiantes · <strong>{totalCapacity}</strong> lugares</p>
              <div
                role="progressbar"
                aria-label="Ocupación de ambientes"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={occupancyPercent}
                aria-valuetext={`${totalStudents ?? 0} estudiantes para ${totalCapacity} lugares`}
                className="mt-2 h-2 overflow-hidden rounded-full bg-sunken"
              >
                <div
                  className={`h-full rounded-full ${capacityDeficit > 0 ? 'bg-warn' : 'bg-ok'}`}
                  style={{ width: `${occupancyPercent}%` }}
                />
              </div>
              {hasSelectedRooms && hasSelectedGroups && capacityDeficit > 0 && (
                <p role="status" className="mt-2 flex gap-2 rounded-lg border border-warn-border bg-warn-soft p-2 text-xs text-warn-fg">
                  <AlertTriangle aria-hidden="true" className="size-4 shrink-0" />
                  Faltan {capacityDeficit} lugares; podrá confirmar el registro de todos modos.
                </p>
              )}
              {(!hasSelectedRooms || !hasSelectedGroups) && (
                <p className="mt-1 text-xs text-muted-foreground">Seleccione ambientes y grupos para comparar la capacidad.</p>
              )}
            </div>
          </div>
        </section>
      </form>

      {/* Modal de confirmación de advertencias */}
      <Dialog
        open={warnings.length > 0 && !createdExam}
        onOpenChange={(open) => {
          if (!open && !submitting) {
            dismissWarnings();
          }
        }}
      >
        <DialogContent
          showCloseButton={false}
          className="sm:max-w-lg"
          onEscapeKeyDown={(event) => {
            if (submitting) event.preventDefault();
          }}
          onInteractOutside={(event) => {
            if (submitting) event.preventDefault();
          }}
        >
          <DialogHeader>
            <div className="flex items-center gap-3">
              <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-warn-soft">
                <AlertTriangle
                  className="size-5 text-warn-fg"
                  aria-hidden="true"
                />
              </div>

              <DialogTitle>
                Revise antes de confirmar
              </DialogTitle>
            </div>

            <DialogDescription className="pt-2">
              Se detectaron situaciones que requieren su
              confirmación antes de registrar el examen.
            </DialogDescription>
          </DialogHeader>

          <div className="max-h-[40vh] overflow-y-auto rounded-lg border border-warn-border bg-warn-soft p-4">
            <ul className="list-disc space-y-3 pl-5 text-sm text-warn-fg">
              {warnings.map((warning, index) => (
                <li key={`${index}-${warning}`}>
                  {warning}
                </li>
              ))}
            </ul>
          </div>

          <DialogFooter className="gap-2 sm:gap-2">
            <Button
              type="button"
              variant="outline"
              disabled={submitting}
              onClick={dismissWarnings}
            >
              Volver al formulario
            </Button>

            <Button
              type="button"
              disabled={submitting}
              onClick={() => void submitExam(true)}
            >
              {submitting ? (
                <>
                  <Loader2 className="size-4 animate-spin" />
                  Registrando...
                </>
              ) : (
                'Crear de todos modos'
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

    </div>
  );
};
