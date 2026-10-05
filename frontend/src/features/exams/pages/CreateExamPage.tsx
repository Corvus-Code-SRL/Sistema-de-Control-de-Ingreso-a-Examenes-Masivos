import React from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { CreateExamContainer } from '../components/CreateExamContainer';

/**
 * Nuevo examen. Va dentro del marco de la aplicación como el resto de pantallas del docente: sin
 * él no hay menú lateral ni miga de pan, y "Cancelar" era la única salida.
 */
export const CreateExamPage: React.FC = () => {
  return (
    <AppShell
      mobileTitle="Nuevo examen"
      breadcrumbs={[{ label: 'Exámenes' }, { label: 'Nuevo examen' }]}
    >
      <CreateExamContainer />
    </AppShell>
  );
};
