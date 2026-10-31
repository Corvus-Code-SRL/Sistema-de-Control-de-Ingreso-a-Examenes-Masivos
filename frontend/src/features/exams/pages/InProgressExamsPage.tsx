import { PlaySquare, RefreshCw } from 'lucide-react';
import { Link } from 'react-router-dom';

import { EmptyState } from '@/components/common/EmptyState';
import { ErrorState } from '@/components/common/ErrorState';
import { LoadingState } from '@/components/common/LoadingState';
import { PageHeader } from '@/components/common/PageHeader';
import { AppShell } from '@/components/layout/AppShell';
import { Button } from '@/components/ui/button';
import { useCurrentUser } from '@/features/auth';

import { useInProgressExams } from '../hooks/useInProgressExams';

/**
 * Lista de acceso a los exámenes EN_CURSO, exclusiva de docente y auxiliar.
 * Los datos salen de useInProgressExams.
 */
export function InProgressExamsPage() {
  const { area } = useCurrentUser();
  const isAssistant = area === 'auxiliar';

  const { status, data, error, reload } = useInProgressExams();

  const exams = data ?? [];

  return (
    <AppShell
      mobileTitle="En curso"
      breadcrumbs={[{ label: 'Exámenes' }, { label: 'En curso' }]}
    >
      <PageHeader
        title="Exámenes en curso"
        subtitle={
          isAssistant
            ? 'Exámenes activos en los que está habilitado como auxiliar.'
            : 'Sus exámenes activos. Puede volver a supervisarlos desde aquí.'
        }
        actions={
          <Button type="button" variant="outline" onClick={reload}>
            <RefreshCw className="size-4" aria-hidden="true" />
            Actualizar
          </Button>
        }
      />

      {status === 'loading' && (
        <LoadingState rows={3} label="Cargando exámenes en curso" />
      )}

      {status === 'error' && error && (
        <ErrorState error={error} onRetry={reload} />
      )}

      {status === 'success' && exams.length === 0 && (
        <EmptyState
          icon={PlaySquare}
          title="No tiene exámenes en curso"
          description="Cuando se inicie un examen en el que participa, aparecerá aquí."
        />
      )}

      {status === 'success' && exams.length > 0 && (
        <ul className="space-y-3" aria-label="Exámenes en curso">
          {exams.map((exam) => (
            <li key={exam.id}>
              <article className="rounded-xl border bg-card p-4 shadow-sm">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="min-w-0 space-y-1">
                    <h2 className="sciem-h3">{exam.title}</h2>
                    {exam.subject && (
                      <p className="text-sm text-muted-foreground">{exam.subject}</p>
                    )}
                    <p className="text-sm text-muted-foreground">
                      {exam.date ?? 'Fecha no disponible'}
                      {exam.startsAt && exam.endsAt
                        ? ` · ${exam.startsAt}–${exam.endsAt}`
                        : ''}
                    </p>
                    <p className="text-sm text-muted-foreground">
                      {isAssistant ? 'Su ambiente' : 'Ambientes'}: {exam.rooms}
                    </p>
                  </div>
                  <Button asChild size="sm">
                    <Link to={`/examenes/${exam.id}/en-curso`}>
                      Ver examen
                    </Link>
                  </Button>
                </div>
              </article>
            </li>
          ))}
        </ul>
      )}
    </AppShell>
  );
}
