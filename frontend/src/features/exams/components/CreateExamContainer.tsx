import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useCreateExam } from '../hooks/useCreateExam';
import { ExamForm } from './ExamForm';
import { Button } from '@/components/ui/button';
import { Loader2, Save } from 'lucide-react';

export const CreateExamContainer: React.FC = () => {
  const navigate = useNavigate();

  const handleSuccess = () => {
    navigate('/exams', { state: { message: 'Examen creado exitosamente' } });
  };

  const {
    formData,
    options,
    loading,
    submitting,
    apiError,
    updateFormData,
    validateForm,
    submitExam,
  } = useCreateExam(handleSuccess);

  const validation = validateForm();

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[350px] gap-3 text-[#6C757D]">
        <Loader2 className="h-7 w-7 animate-spin text-[#005E68]" />
        <p className="text-xs font-medium">Cargando formulario y catálogo de materias...</p>
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

        <div className="flex items-center justify-end gap-3 pt-2">
          <Button
            type="button"
            variant="outline"
            onClick={() => navigate('/exams')}
            disabled={submitting}
            className="bg-white border-[#DDDDDD] text-[#2C2C2C] hover:bg-gray-50 text-xs font-medium px-5 py-2.5 rounded-lg"
          >
            Cancelar
          </Button>

          <Button
            type="submit"
            disabled={submitting}
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
