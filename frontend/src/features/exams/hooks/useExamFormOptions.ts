import { useState, useEffect } from 'react';
import { ExamFormOptions } from '../types/exams.types';
import { examsService } from '../services/examsService';

export function useExamFormOptions() {
  const [options, setOptions] = useState<ExamFormOptions>({
    materias: [],
    ambientes: [],
    grupos: [],
  });
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;
    async function loadOptions() {
      try {
        setLoading(true);
        const data = await examsService.getFormData();
        if (isMounted) {
          setOptions(data);
          setError(null);
        }
      } catch (err: unknown) {
        if (isMounted) {
          const message = err instanceof Error ? err.message : 'Error al cargar opciones del formulario';
          setError(message);
        }
      } finally {
        if (isMounted) {
          setLoading(false);
        }
      }
    }
    loadOptions();
    return () => {
      isMounted = false;
    };
  }, []);

  return {
    options,
    loading,
    error,
  };
}
