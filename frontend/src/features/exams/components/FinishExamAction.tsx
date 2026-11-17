import { useRef, useState } from 'react';
import { Square, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { ApiError } from '@/lib/api-client';
import { examsService } from '../services/examsService';
import type { Exam } from '../types/exams.types';
import { ExamStatusBadge } from './ExamStatusBadge';

interface FinishExamActionProps {
  exam: Exam;
  onFinished: (exam: Exam) => void;
  onConflict: () => void;
}

export function FinishExamAction({ exam, onFinished, onConflict }: FinishExamActionProps) {
  const [open, setOpen] = useState(false);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const inFlight = useRef(false);
  const cancelButton = useRef<HTMLButtonElement>(null);

  function changeOpen(nextOpen: boolean) {
    if (inFlight.current) return;
    setError(null);
    setOpen(nextOpen);
  }

  async function finish() {
    if (inFlight.current) return;
    inFlight.current = true;
    setPending(true);
    setError(null);

    try {
      const finishedExam = await examsService.finishExam(exam.id_examen);
      setOpen(false);
      onFinished(finishedExam);
    } catch (cause) {
      if (cause instanceof ApiError && cause.status === 409) {
        setOpen(false);
        onConflict();
      } else if (cause instanceof ApiError && cause.isForbidden) {
        setError('No tiene autorización para finalizar este examen');
      } else {
        setError('No se pudo terminar el examen. Intente nuevamente.');
      }
    } finally {
      inFlight.current = false;
      setPending(false);
    }
  }

  const schedule = [exam.hora_inicio, exam.hora_fin].filter(Boolean).join('–');

  return (
    <Dialog open={open} onOpenChange={changeOpen}>
      <DialogTrigger asChild>
        <Button type="button" variant="danger" aria-label={`Terminar examen: ${exam.nombre_examen}`}>
          <Square aria-hidden="true" /> Terminar examen
        </Button>
      </DialogTrigger>
      <DialogContent
        showCloseButton={false}
        className="sm:max-w-md"
        onOpenAutoFocus={(event) => {
          event.preventDefault();
          cancelButton.current?.focus();
        }}
      >
        <DialogClose asChild>
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            className="absolute right-2 top-2"
            aria-label="Cerrar confirmación"
            disabled={pending}
          >
            <X aria-hidden="true" />
          </Button>
        </DialogClose>
        <DialogHeader className="pr-8">
          <DialogTitle>¿Terminar el examen?</DialogTitle>
          <DialogDescription className="break-words font-medium text-foreground">
            {exam.nombre_examen}
          </DialogDescription>
        </DialogHeader>
        <dl className="grid grid-cols-2 gap-3 rounded-lg bg-sunken p-3">
          <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">Materia</dt>
            <dd className="break-words">{exam.materia?.nombre ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted-foreground">Fecha</dt>
            <dd>{exam.fecha ? <time dateTime={exam.fecha}>{exam.fecha}</time> : '—'}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted-foreground">Horario programado</dt>
            <dd>{schedule || '—'}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted-foreground">Estado</dt>
            <dd><ExamStatusBadge status={exam.estado} /></dd>
          </div>
        </dl>
        {error && <p role="alert" className="text-sm text-destructive">{error}</p>}
        <DialogFooter>
          <DialogClose asChild>
            <Button ref={cancelButton} type="button" variant="outline" disabled={pending}>
              Seguir en curso
            </Button>
          </DialogClose>
          <Button type="button" variant="danger" disabled={pending} onClick={() => void finish()}>
            <Square aria-hidden="true" /> {pending ? 'Terminando examen…' : 'Terminar examen'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
