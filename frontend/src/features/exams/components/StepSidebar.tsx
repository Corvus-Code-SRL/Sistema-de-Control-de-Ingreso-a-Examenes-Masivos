import React from 'react';
import { Check, AlertCircle } from 'lucide-react';
import { StepStatus } from '../types/exams.types';

interface StepDef {
  label: string;
  status: StepStatus;
}

interface Props {
  steps: StepDef[];
  currentStep: number;
  onStepClick: (stepIndex: number) => void;
}

export const StepSidebar: React.FC<Props> = ({ steps, currentStep, onStepClick }) => {
  return (
    <nav className="bg-white rounded-xl border border-[#E5E7EB] p-5 shadow-xs h-fit sticky top-6">
      <ul className="space-y-1">
        {steps.map((step, idx) => {
          const stepNum = idx + 1;
          const isActive = stepNum === currentStep;
          const isClickable = step.status === 'completed' || isActive;

          return (
            <li key={stepNum}>
              <button
                type="button"
                onClick={() => isClickable && onStepClick(stepNum)}
                disabled={!isClickable}
                className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left transition-colors ${isActive
                    ? 'bg-[#EFF8F8] text-[#005E68]'
                    : isClickable
                      ? 'hover:bg-gray-50 text-[#374151] cursor-pointer'
                      : 'text-[#9CA3AF] cursor-default'
                  }`}
              >
                {/* Indicador del paso */}
                <span className="shrink-0">
                  {step.status === 'completed' ? (
                    <span className="flex items-center justify-center h-6 w-6 rounded-full bg-[#005E68] text-white">
                      <Check className="h-3.5 w-3.5 stroke-[3]" />
                    </span>
                  ) : step.status === 'error' ? (
                    <span className="flex items-center justify-center h-6 w-6 rounded-full bg-red-100 text-red-600">
                      <AlertCircle className="h-3.5 w-3.5" />
                    </span>
                  ) : isActive ? (
                    <span className="flex items-center justify-center h-6 w-6 rounded-full bg-[#005E68] text-white text-xs font-bold">
                      {stepNum}
                    </span>
                  ) : (
                    <span className="flex items-center justify-center h-6 w-6 rounded-full border border-[#D1D5DB] text-[#9CA3AF] text-xs font-medium">
                      {stepNum}
                    </span>
                  )}
                </span>

                {/* Nombre del paso */}
                <span
                  className={`text-sm font-medium ${isActive
                      ? 'text-[#005E68] font-semibold'
                      : step.status === 'error'
                        ? 'text-red-600 font-semibold'
                        : step.status === 'completed'
                          ? 'text-[#374151]'
                          : 'text-[#9CA3AF]'
                    }`}
                >
                  {step.label}
                </span>
              </button>
            </li>
          );
        })}
      </ul>
    </nav>
  );
};
