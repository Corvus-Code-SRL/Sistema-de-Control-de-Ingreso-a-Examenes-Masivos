import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
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
        <Button
          type="button"
          onClick={() => {
            resetFormData();
            setCreatedExam(null);
          }}
          className="bg-[#005E68] hover:bg-[#00555E] text-white text-xs font-semibold px-6 py-2.5 rounded-lg"
        >
          Crear otro examen
        </Button>
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
            onClick={() => navigate(-1)}
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
