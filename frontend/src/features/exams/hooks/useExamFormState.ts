import { useState, useCallback } from 'react';
import { CreateExamFormData } from '../types/exams.types';

const defaultFormData: CreateExamFormData = {
  nombre_examen: '',
  id_materia: null,
  categoria: 'REGULAR',
  fecha: new Date().toISOString().split('T')[0],
  hora_inicio: '08:00',
  duracion: 90,
  ambientes: [],
  grupos: [],
  normas: '',
};

export function useExamFormState(initialState: CreateExamFormData = defaultFormData) {
  const [formData, setFormData] = useState<CreateExamFormData>(initialState);

  const updateFormData = useCallback((fields: Partial<CreateExamFormData>) => {
    setFormData((prev) => ({ ...prev, ...fields }));
  }, []);

  const resetFormData = useCallback(() => {
    setFormData(defaultFormData);
  }, []);

  return {
    formData,
    updateFormData,
    resetFormData,
  };
}
