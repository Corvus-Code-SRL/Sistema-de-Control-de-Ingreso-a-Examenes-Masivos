import { useEffect, useRef, useState } from 'react';
import { CheckCircle2, RefreshCw, Square } from 'lucide-react';
import { EmptyState } from '@/components/common/EmptyState';
import { ErrorState } from '@/components/common/ErrorState';
import { ExamSummaryCard } from '@/components/common/ExamSummaryCard';
import { LoadingState } from '@/components/common/LoadingState';
import { PageHeader } from '@/components/common/PageHeader';
import { StatePanel } from '@/components/common/StatePanel';
import { AppShell } from '@/components/layout/AppShell';
import { Button } from '@/components/ui/button';
import { useAsyncResource } from '@/hooks/useAsyncResource';
import { FinishExamAction } from '../components/FinishExamAction';
import { examsService } from '../services/examsService';
import { EXAM_STATUS, type Exam } from '../types/exams.types';

interface FinishFeedback {
  examId: number;
  result: 'finished' | 'unavailable';
}

export function FinishExamsPage() {
  // La consulta del servidor devuelve únicamente los exámenes del titular autenticado.
  const { data, status, error, reload } = useAsyncResource(examsService.listExams, []);
  const [results, setResults] = useState<FinishFeedback[]>([]);
  const feedbackRef = useRef<HTMLDivElement>(null);
  const feedback = results[results.length - 1];

  const candidates = (data ?? []).filter(
    (exam) => exam.estado === EXAM_STATUS.EN_CURSO && !results.some(({ examId }) => examId === exam.id_examen)
  );

  useEffect(() => {
    if (feedback) feedbackRef.current?.focus();
  }, [feedback]);

  function removeCandidate(exam: Exam, result: FinishFeedback['result']) {
    setResults((previous) => [...previous, { examId: exam.id_examen, result }]);
    if (result === 'unavailable') reload();
  }

  return (
    <AppShell mobileTitle="Terminar examen" breadcrumbs={[{ label: 'Exámenes' }, { label: 'Terminar examen' }]}>
      <PageHeader
        title="Terminar examen"
        actions={
          <Button type="button" variant="outline" onClick={reload}>
            <RefreshCw aria-hidden="true" /> Actualizar
          </Button>
        }
      />

      {feedback && (
        <div key={feedback.examId} ref={feedbackRef} tabIndex={-1} className="rounded-xl">
          {feedback.result === 'finished' ? (
            <StatePanel tone="ok" icon={CheckCircle2} title="Examen finalizado correctamente." role="status" />
          ) : (
            <p role="status" className="rounded-xl border border-info-border bg-info-soft p-4 text-sm text-info">
              El examen ya no está disponible para finalizar.
            </p>
          )}
        </div>
      )}

      {status === 'loading' && <LoadingState rows={3} label="Cargando exámenes disponibles para finalizar" />}
      {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}
      {status === 'success' && candidates.length === 0 && (
        <EmptyState
          icon={Square}
          title="No tiene exámenes disponibles para finalizar"
          description="Sus exámenes en curso aparecerán aquí."
        />
      )}
      {status === 'success' && candidates.length > 0 && (
        <ul className="space-y-3" aria-label="Exámenes disponibles para finalizar">
          {candidates.map((exam) => (
            <li key={exam.id_examen}>
              <ExamSummaryCard
                title={exam.nombre_examen}
                subject={exam.materia?.nombre ?? null}
                dateLabel={exam.fecha || 'Fecha no disponible'}
                startsAt={exam.hora_inicio}
                endsAt={exam.hora_fin}
                roomsLabel="Ambientes"
                rooms={exam.ambientes?.map((room) => room.nro_aula).join(', ') || 'Sin ambiente'}
                action={
                  <FinishExamAction
                    exam={exam}
                    onFinished={(finishedExam) => removeCandidate(finishedExam, 'finished')}
                    onConflict={() => removeCandidate(exam, 'unavailable')}
                  />
                }
              />
            </li>
          ))}
        </ul>
      )}
    </AppShell>
  );
}
