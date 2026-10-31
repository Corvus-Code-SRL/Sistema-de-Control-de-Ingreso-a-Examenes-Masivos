import { useState, useCallback } from 'react';
import { CreateExamFormData } from '../types/exams.types';
import { localToday } from '../utils/examValidators';

function emptyForm(): CreateExamFormData {
  return {
    nombre_examen: '',
    materia: null,
    categoria: 'REGULAR',
    fecha: localToday(),
    hora_inicio: '08:00',
    duracion: 90,
    ambientes: [],
    grupos: [],
    normas: '',
  };
}

export function useExamFormState(initialState?: CreateExamFormData) {
  const [formData, setFormData] = useState<CreateExamFormData>(() => initialState ?? emptyForm());

  const updateFormData = useCallback((fields: Partial<CreateExamFormData>) => {
    setFormData((prev) => ({ ...prev, ...fields }));
  }, []);

  const resetFormData = useCallback(() => {
    setFormData(emptyForm());
  }, []);

  return {
    formData,
    updateFormData,
    resetFormData,
  };
}
