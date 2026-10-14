import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useCreateExam } from '../hooks/useCreateExam';
import { ExamForm } from './ExamForm';
import { AssignGroupsForm } from './AssignGroupsForm';
import { Button } from '@/components/ui/button';
import { AlertTriangle, CheckCircle2, Loader2, Save } from 'lucide-react';
import { Exam } from '../types/exams.types';

export const CreateExamContainer: React.FC = () => {
  const navigate = useNavigate();
  const [createdExam, setCreatedExam] = useState<Exam | null>(null);

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
      <div className="flex flex-col items-center justify-center min-h-[350px] gap-3 text-[#6C757D]">
        <Loader2 className="h-7 w-7 animate-spin text-[#005E68]" />
        <p className="text-xs font-medium">Cargando formulario y catálogo de materias...</p>
      </div>
    );
  }

  if (createdExam) {
    return (
      <div className="max-w-3xl mx-auto bg-white rounded-xl border border-[#DDDDDD] p-10 text-center space-y-4">
        <CheckCircle2 className="h-10 w-10 text-[#15803D] mx-auto" />
        <h1 className="text-lg font-bold text-[#2C2C2C]">Examen creado</h1>
        <p className="text-xs text-[#6C757D]">
          «{createdExam.nombre_examen}» quedó registrado con sus grupos en estado{' '}
          <strong>Programado</strong> para el {createdExam.fecha} de{' '}
          {createdExam.hora_inicio} a {createdExam.hora_fin}.
        </p>
        <div className="flex flex-wrap items-center justify-center gap-3 pt-2">
          <Button asChild className="bg-[#005E68] hover:bg-[#00555E] text-white text-xs font-semibold px-6 py-2.5 rounded-lg">
            <Link to={`/examenes/${createdExam.id_examen}`}>Configurar auxiliares y ambientes</Link>
          </Button>
          <Button asChild variant="outline" className="text-xs font-semibold px-6 py-2.5 rounded-lg">
            <Link to="/examenes/programados">Ver en Programados</Link>
          </Button>
          <Button
            type="button"
            variant="ghost"
            onClick={() => {
              resetFormData();
              setCreatedExam(null);
            }}
            className="text-xs font-medium px-4 py-2.5 rounded-lg"
          >
            Crear otro examen
          </Button>
        </div>
      </div>
    );
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (validation.isValid) {
      submitExam();
    }
  };

  return (
    <div className="space-y-6 max-w-5xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-[#2C2C2C]">
          Configurar nuevo examen
        </h1>
        <p className="text-xs text-[#6C757D] mt-0.5">
          Crear un nuevo examen para la materia seleccionada.
        </p>
      </div>

      {apiError && (
        <div className="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-700 text-xs font-medium">
          {apiError}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-6">
        <ExamForm
          formData={formData}
          subjects={options.materias}
          classrooms={options.ambientes}
          errors={validation.errors}
          warnings={validation.warnings}
          updateFormData={updateFormData}
        />

        <AssignGroupsForm
          selectedSubject={selectedSubject}
          availableGroups={subjectGroups}
          selectedGroupIds={formData.grupos}
          errors={validation.errors}
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

        {warnings.length > 0 && (
          <div
            role="alert"
            className="p-4 rounded-xl bg-[#FFF3C7] border border-[#F2D98A] text-[#7A5800] text-xs space-y-3"
          >
            <p className="font-bold flex items-center gap-2">
              <AlertTriangle className="h-4 w-4" /> Revise antes de confirmar
            </p>
            <ul className="list-disc pl-5 space-y-1">
              {warnings.map((warning) => (
                <li key={warning}>{warning}</li>
              ))}
            </ul>
            <Button
              type="button"
              disabled={submitting}
              onClick={() => submitExam(true)}
              className="bg-[#9A6F00] hover:bg-[#7A5800] text-white text-xs font-semibold px-4 py-2 rounded-lg"
            >
              Crear el examen de todos modos
            </Button>
          </div>
        )}

        <div className="flex items-center justify-end gap-3 pt-2">
          <Button
            type="button"
            variant="outline"
            onClick={() => navigate('/examenes/programados')}
            disabled={submitting}
            className="bg-white border-[#DDDDDD] text-[#2C2C2C] hover:bg-gray-50 text-xs font-medium px-5 py-2.5 rounded-lg"
          >
            Cancelar
          </Button>

          <Button
            type="submit"
            disabled={submitting || warnings.length > 0}
            className="bg-[#005E68] hover:bg-[#00555E] text-white text-xs font-semibold px-6 py-2.5 rounded-lg shadow-xs flex items-center gap-2"
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
    </div>
  );
};
