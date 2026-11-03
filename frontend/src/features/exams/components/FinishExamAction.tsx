import { useRef, useState } from 'react';
import { Square } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { ApiError } from '@/lib/api-client';
import { examsService } from '../services/examsService';
import type { ExamInProgress } from '../types/examInProgress.types';

const campusTime = new Intl.DateTimeFormat('es-BO', {
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
  timeZone: 'America/La_Paz',
});

export function FinishExamAction({ exam }: { exam: ExamInProgress }) {
  if (exam.estado === 'FINALIZADO') {
    return exam.fin_real_en && (
      <span className="text-xs text-muted-foreground">
        Fin real <time dateTime={exam.fin_real_en}>{campusTime.format(new Date(exam.fin_real_en))}</time>
      </span>
    );
  }

  return exam.estado === 'EN_CURSO' && exam.rol_controlador === 'DOCENTE'
    ? <FinishExamDialog exam={exam} />
    : null;
}

function FinishExamDialog({ exam }: { exam: ExamInProgress }) {
  const [open, setOpen] = useState(false);
  const [pending, setPending] = useState(false);
  const [finished, setFinished] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const inFlight = useRef(false);

  function changeOpen(nextOpen: boolean) {
    if (inFlight.current) return;
    setError(null);
    setOpen(nextOpen);
  }

  async function finish() {
    if (inFlight.current || finished) return;
    inFlight.current = true;
    setPending(true);
    setError(null);

    try {
      await examsService.finishExam(exam.id_examen);
      setFinished(true);
      setOpen(false);
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : 'No se pudo terminar el examen. Intente nuevamente.');
    } finally {
      inFlight.current = false;
      setPending(false);
    }
  }

  if (finished) {
    return <p role="status" className="text-sm">Examen finalizado correctamente.</p>;
  }

  const totals = [
    ['A tiempo', exam.totales.a_tiempo],
    ['Con retraso', exam.totales.con_retraso],
    ['Ausentes', exam.totales.ausentes],
    ['Incidencias pendientes', exam.totales.incidencias_pendientes],
  ] as const;

  return (
    <Dialog open={open} onOpenChange={changeOpen}>
      <DialogTrigger asChild>
        <Button type="button" variant="danger">
          <Square aria-hidden="true" /> Terminar examen
        </Button>
      </DialogTrigger>
      <DialogContent showCloseButton={false} className="sm:max-w-xl">
        <DialogHeader className="flex-row items-center gap-3">
          <span className="rounded-lg bg-destructive/10 p-2 text-destructive">
            <Square className="size-5" aria-hidden="true" />
          </span>
          <DialogTitle>¿Terminar el examen?</DialogTitle>
        </DialogHeader>
        <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
          {totals.map(([label, value]) => (
            <div key={label} className="flex flex-col-reverse justify-end gap-1 rounded-lg bg-sunken p-3">
              <dt className="text-xs text-muted-foreground">{label}</dt>
              <dd className="sciem-tnum text-xl font-semibold">{value}</dd>
            </div>
          ))}
        </dl>
        <DialogDescription>
          Los estudiantes que no ingresaron quedan como ausentes. Las incidencias pendientes se revisan después.
        </DialogDescription>
        {error && <p role="alert" className="text-sm text-destructive">{error}</p>}
        <DialogFooter>
          <Button type="button" variant="outline" disabled={pending} onClick={() => changeOpen(false)}>
            Seguir en curso
          </Button>
          <Button type="button" variant="danger" disabled={pending} onClick={() => void finish()}>
            <Square aria-hidden="true" /> {pending ? 'Terminando examen…' : 'Terminar examen'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
