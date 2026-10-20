import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useCreateExam } from '../hooks/useCreateExam';
import { ExamForm } from './ExamForm';
import { AssignGroupsForm } from './AssignGroupsForm';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { AlertTriangle, CheckCircle2, Loader2, Save } from 'lucide-react';
import { Exam } from '../types/exams.types';

export const CreateExamContainer: React.FC = () => {
  const navigate = useNavigate();
  const [createdExam, setCreatedExam] = useState<Exam | null>(null);
  const [showValidationErrors, setShowValidationErrors] = useState(false);

  const {
    formData,
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
  } = useCreateExam(setCreatedExam);

  const validation = validateForm();
  const selectedSubject = options.materias.find(
    (subject) =>
      subject.id_carrera === formData.materia?.id_carrera &&
      subject.id_materia === formData.materia?.id_materia
  );

  const totalCapacity = options.ambientes
    .filter((room) => formData.ambientes.includes(room.id_ambiente))
    .reduce((total, room) => total + Number(room.capacidad), 0);

  const hasSelectedRooms = formData.ambientes.length > 0;
  const hasSelectedGroups = formData.grupos.length > 0;

  const capacityDeficit =
    hasSelectedRooms && hasSelectedGroups
      ? Math.max(0, totalStudents - totalCapacity)
      : 0;


  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[350px] gap-3 text-muted-foreground">
        <Loader2 className="h-7 w-7 animate-spin text-primary" />
        <p className="text-xs font-medium">Cargando formulario y catálogo de materias...</p>
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
    <div className="space-y-6 max-w-5xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-foreground">
          Configurar nuevo examen
        </h1>
        <p className="text-xs text-muted-foreground mt-0.5">
          Crear un nuevo examen para la materia seleccionada.
        </p>
      </div>

      {apiError && (
        <div className="p-4 rounded-xl bg-danger-soft border border-danger/20 text-danger-fg text-xs font-medium">
          {apiError}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-6">
        <ExamForm
          formData={formData}
          subjects={options.materias}
          classrooms={options.ambientes}
          errors={showValidationErrors ? validation.errors : {}}
          warnings={validation.warnings}
          updateFormData={updateFormData}
        />

        <AssignGroupsForm
          selectedSubject={selectedSubject}
          availableGroups={subjectGroups}
          selectedGroupIds={formData.grupos}
          errors={showValidationErrors ? validation.errors : {}}
          onToggleGroup={toggleGroup}
        />

        {(hasSelectedRooms || hasSelectedGroups) && (
          <section
            aria-label="Resumen de capacidad del examen"
            aria-live="polite"
            className="rounded-xl border border-border-soft bg-surface p-6 space-y-4"
          >
            <div>
              <h2 className="text-base font-bold text-text">
                Resumen de capacidad
              </h2>
              <p className="mt-1 text-xs text-muted-foreground">
                Compare las plazas disponibles con los estudiantes
                de los grupos seleccionados.
              </p>
            </div>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <div className="rounded-lg bg-sunken p-4">
                <p className="text-xs text-muted-foreground">
                  Capacidad total
                </p>
                <p className="mt-1 text-xl font-bold text-text">
                  {totalCapacity}
                </p>
                <p className="text-xs text-muted-foreground">
                  plazas disponibles
                </p>
              </div>

              <div className="rounded-lg bg-sunken p-4">
                <p className="text-xs text-muted-foreground">
                  Estudiantes
                </p>
                <p className="mt-1 text-xl font-bold text-text">
                  {totalStudents}
                </p>
                <p className="text-xs text-muted-foreground">
                  inscritos en los grupos
                </p>
              </div>

              <div className="rounded-lg bg-sunken p-4">
                <p className="text-xs text-muted-foreground">
                  Plazas faltantes
                </p>
                <p
                  className={`mt-1 text-xl font-bold ${
                    capacityDeficit > 0 ? 'text-danger' : 'text-text'
                  }`}
                >
                  {capacityDeficit}
                </p>
                <p className="text-xs text-muted-foreground">
                  respecto a los estudiantes
                </p>
              </div>
            </div>

            {hasSelectedRooms && hasSelectedGroups && capacityDeficit > 0 && (
              <div
                role="status"
                className="flex items-start gap-2 rounded-lg border border-border-soft p-3"
              >
                <AlertTriangle
                  aria-hidden="true"
                  className="h-4 w-4 shrink-0 text-danger"
                />
                <p className="text-xs text-text">
                  La capacidad seleccionada es insuficiente para
                  todos los estudiantes. El servidor solicitará
                  confirmación antes de registrar el examen.
                </p>
              </div>
            )}

            {(!hasSelectedRooms || !hasSelectedGroups) && (
              <p className="text-xs text-muted-foreground">
                Seleccione ambientes y grupos para completar
                la comparación de capacidad.
              </p>
            )}
          </section>
        )}

        <div className="flex items-center justify-end gap-3 pt-2">
          <Button
            type="button"
            variant="outline"
            onClick={() => navigate('/examenes/programados')}
            disabled={submitting}
            className="bg-card border-border text-foreground hover:bg-muted text-xs font-medium px-5 py-2.5 rounded-lg"
          >
            Cancelar
          </Button>

          <Button
            type="submit"
            disabled={submitting || warnings.length > 0}
            className="bg-primary hover:bg-primary/90 text-primary-foreground text-xs font-semibold px-6 py-2.5 rounded-lg shadow-xs flex items-center gap-2"
          >
            {submitting ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" /> Guardando...
              </>
            ) : (
              <>
                <Save className="h-4 w-4" /> Guardar examen
              </>
            )}
          </Button>
        </div>
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

      {/* Modal de registro exitoso */}
      <Dialog
        open={createdExam !== null}
        onOpenChange={() => {
          // La creación ya terminó: el usuario debe elegir
          // una de las tres acciones explícitas.
        }}
      >
        <DialogContent
          showCloseButton={false}
          className="sm:max-w-lg"
          onEscapeKeyDown={(event) => event.preventDefault()}
          onInteractOutside={(event) => event.preventDefault()}
        >
          <DialogHeader>
            <div className="flex justify-center pb-2">
              <div className="flex size-14 items-center justify-center rounded-full bg-brand-soft">
                <CheckCircle2
                  className="size-8 text-brand"
                  aria-hidden="true"
                />
              </div>
            </div>

            <DialogTitle className="text-center text-lg">
              Examen creado correctamente
            </DialogTitle>

            <DialogDescription className="text-center">
              El examen se registró exitosamente y se encuentra
              en estado Programado.
            </DialogDescription>
          </DialogHeader>

          {createdExam && (
            <>
              <div className="rounded-lg border border-border-soft bg-sunken p-4 text-sm">
                <p className="font-semibold text-text">
                  {createdExam.nombre_examen}
                </p>

                <p className="mt-2 text-muted-foreground">
                  Fecha: {createdExam.fecha}
                </p>

                <p className="text-muted-foreground">
                  Horario: {createdExam.hora_inicio} – {createdExam.hora_fin}
                </p>
              </div>

              <div className="flex flex-col gap-2 pt-2">
                <Button asChild>
                  <Link to={`/examenes/${createdExam.id_examen}`}>
                    Ver detalle del examen
                  </Link>
                </Button>

                <Button asChild variant="outline">
                  <Link to="/examenes/programados">
                    Ver en Programados
                  </Link>
                </Button>

                <Button
                  type="button"
                  variant="ghost"
                  onClick={() => {
                    resetFormData();
                    setShowValidationErrors(false);
                    setCreatedExam(null);
                  }}
                >
                  Crear otro examen
                </Button>
              </div>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
};
