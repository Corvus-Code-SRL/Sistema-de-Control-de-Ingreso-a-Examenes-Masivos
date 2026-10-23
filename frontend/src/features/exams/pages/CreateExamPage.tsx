import React from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { groupExamsPath, groupIdFrom, MY_COURSES_PATH } from '@/features/groups';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { AppShell } from '@/components/layout/AppShell';
import { CreateExamContainer } from '../components/CreateExamContainer';

/**
 * Nuevo examen. Va dentro del marco de la aplicación como el resto de pantallas del docente: sin
 * él no hay menú lateral ni miga de pan, y "Cancelar" era la única salida.
 */
export const CreateExamPage: React.FC = () => {
  const [params] = useSearchParams();
  const rawGroup = params.get('grupo');
  const groupId = rawGroup === null ? undefined : groupIdFrom(rawGroup);
  return (
    <AppShell
      mobileTitle="Nuevo examen"
      breadcrumbs={groupId
        ? [{ label: 'Exámenes del grupo', to: groupExamsPath(groupId) }, { label: 'Nuevo examen' }]
        : [{ label: 'Exámenes' }, { label: 'Nuevo examen' }]}
    >
      {groupId === null ? (
        <div className="space-y-4">
          <Alert variant="destructive">
            <AlertDescription>El identificador del grupo no es válido.</AlertDescription>
          </Alert>
          <Button asChild variant="outline">
            <Link to={MY_COURSES_PATH}>Volver a Mis cursos</Link>
          </Button>
        </div>
      ) : <CreateExamContainer key={groupId ?? 'general'} initialGroupId={groupId} />}
    </AppShell>
  );
};
