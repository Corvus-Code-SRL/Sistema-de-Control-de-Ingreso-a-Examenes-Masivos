import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCreateExam } from '../hooks/useCreateExam';
import { ExamForm } from './ExamForm';
import { AssignGroupsForm } from './AssignGroupsForm';
import { AssignClassroomsForm } from './AssignClassroomsForm';
import { StepSidebar } from './StepSidebar';
import { SummaryPanel } from './SummaryPanel';
import { Loader2, ArrowRight, ArrowLeft, CheckCircle2 } from 'lucide-react';
import { StepStatus } from '../types/exams.types';
import { useLayoutContext } from '@/components/layout/MainLayout';

const STEP_LABELS = ['Datos generales', 'Grupos', 'Ambientes'];

export const CreateExamContainer: React.FC = () => {
  const navigate = useNavigate();
  const { setCustomBreadcrumbs } = useLayoutContext();

  const handleSuccess = () => {
    navigate('/exams', { state: { message: 'Examen y grupos asignados exitosamente' } });
  };

  const {
    currentStep,
    formData,
    options,
    subjectGroups,
    selectedGroups,
    totalStudents,
    loadingGroups,
    loading,
    submitting,
    apiError,
    stepErrors,
    completedSteps,
    errorSteps,
    updateFormData,
    goToStep2,
    goToStep3,
    goToStep,
    goBack,
    toggleGroup,
    toggleClassroom,
    confirmAndCreate,
  } = useCreateExam(handleSuccess);

  const selectedSubject = options.materias.find((m) => m.id_materia === formData.id_materia);

  useEffect(() => {
    if (selectedSubject?.nombre) {
      setCustomBreadcrumbs([{ label: selectedSubject.nombre }]);
    } else {
      setCustomBreadcrumbs([]);
    }
    return () => setCustomBreadcrumbs([]);
  }, [selectedSubject, setCustomBreadcrumbs]);

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[350px] gap-3 text-[#6C757D]">
        <Loader2 className="h-7 w-7 animate-spin text-[#005E68]" />
        <p className="text-sm font-medium">Cargando formulario y catálogo del sistema...</p>
      </div>
    );
  }


  // Compute step statuses for StepSidebar
  const steps = STEP_LABELS.map((label, idx) => {
    const stepNum = idx + 1;
    let status: StepStatus = 'pending';
    if (errorSteps.has(stepNum)) status = 'error';
    else if (stepNum === currentStep) status = 'active';
    else if (completedSteps.has(stepNum)) status = 'completed';
    return { label, status };
  });

  // Determine button actions based on current step
  const handleNext = () => {
    if (currentStep === 1) goToStep2();
    else if (currentStep === 2) goToStep3();
    else if (currentStep === 3) confirmAndCreate();
  };

  const isLastStep = currentStep === 3;

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-[#111827]">
          Nuevo examen
        </h1>
        <p className="text-sm text-[#6B7280] mt-0.5">
          Complete los pasos. Puede volver a cualquier paso ya completado.
        </p>
      </div>

      {/* API Error Banner */}
      {apiError && (
        <div className="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-700 text-sm font-medium">
          {apiError}
        </div>
      )}

      {/* 3-column layout */}
      <div className="grid grid-cols-1 lg:grid-cols-[200px_1fr_240px] gap-6">
        {/* Left: Step Sidebar */}
        <div className="hidden lg:block">
          <StepSidebar
            steps={steps}
            currentStep={currentStep}
            onStepClick={(step) => goToStep(step as 1 | 2 | 3)}
          />
        </div>

        {/* Mobile stepper (visible on small screens) */}
        <div className="lg:hidden flex items-center gap-2 overflow-x-auto pb-2">
          {steps.map((step, idx) => {
            const stepNum = idx + 1;
            const isActive = stepNum === currentStep;
            return (
              <button
                key={stepNum}
                type="button"
                onClick={() => (completedSteps.has(stepNum) || isActive) && goToStep(stepNum as 1 | 2 | 3)}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors ${
                  isActive
                    ? 'bg-[#005E68] text-white'
                    : step.status === 'completed'
                    ? 'bg-[#EFF8F8] text-[#005E68]'
                    : step.status === 'error'
                    ? 'bg-red-50 text-red-600'
                    : 'bg-gray-100 text-[#9CA3AF]'
                }`}
              >
                <span>{stepNum}</span>
                <span>{step.label}</span>
              </button>
            );
          })}
        </div>

        {/* Center: Step Content */}
        <div className="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
          <div className="p-6 md:p-8">
            {/* Step-specific content */}
            {currentStep === 1 && (
              <ExamForm
                formData={formData}
                subjects={options.materias}
                errors={stepErrors}
                updateFormData={updateFormData}
              />
            )}

            {currentStep === 2 && (
              <AssignGroupsForm
                selectedSubject={selectedSubject}
                availableGroups={subjectGroups}
                selectedGroupIds={formData.grupos}
                loadingGroups={loadingGroups}
                errors={stepErrors}
                onToggleGroup={toggleGroup}
              />
            )}

            {currentStep === 3 && (
              <AssignClassroomsForm
                classrooms={options.ambientes}
                selectedClassroomIds={formData.ambientes}
                totalStudents={totalStudents}
                errors={stepErrors}
                onToggleClassroom={toggleClassroom}
              />
            )}
          </div>

          {/* Footer buttons */}
          <div className="px-6 md:px-8 py-4 border-t border-[#F3F4F6] flex items-center justify-between">
            <button
              type="button"
              onClick={() => navigate('/exams')}
              disabled={submitting}
              className="text-sm font-semibold text-[#005E68] hover:text-[#004D56] transition-colors cursor-pointer disabled:opacity-50"
            >
              Cancelar
            </button>

            <div className="flex items-center gap-3">
              {currentStep > 1 && (
                <button
                  type="button"
                  onClick={goBack}
                  disabled={submitting}
                  className="px-4 py-2 rounded-lg border border-[#D1D5DB] bg-white text-[#374151] hover:bg-gray-50 text-sm font-semibold transition-colors flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                >
                  <ArrowLeft className="h-4 w-4" />
                  Atrás
                </button>
              )}

              <button
                type="button"
                onClick={handleNext}
                disabled={submitting || loadingGroups}
                className={`px-5 py-2 rounded-lg text-white text-sm font-semibold transition-colors flex items-center gap-2 cursor-pointer disabled:opacity-50 ${
                  isLastStep
                    ? 'bg-[#005E68] hover:bg-[#004D56]'
                    : 'bg-[#005E68] hover:bg-[#004D56]'
                }`}
              >
                {submitting ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" />
                    {isLastStep ? 'Creando examen...' : 'Cargando...'}
                  </>
                ) : isLastStep ? (
                  <>
                    <CheckCircle2 className="h-4 w-4" />
                    Confirmar
                  </>
                ) : (
                  <>
                    Continuar
                    <ArrowRight className="h-4 w-4" />
                  </>
                )}
              </button>
            </div>
          </div>
        </div>

        {/* Right: Summary Panel */}
        <div className="hidden lg:block">
          <SummaryPanel
            formData={formData}
            subjects={options.materias}
            classrooms={options.ambientes}
            selectedGroups={selectedGroups}
          />
        </div>
      </div>
    </div>
  );
};
