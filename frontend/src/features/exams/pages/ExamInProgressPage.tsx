import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { AppShell } from '@/components/layout/AppShell';
import { Button } from '@/components/ui/button';
import { normalizeSearch } from '@/lib/text';
import { examInProgressService } from '../services/examInProgressService';
import type { ExamInProgressExtensions } from '../components/ExamInProgressExtensions';
import type {
  ExamInProgress,
  InProgressStudent,
} from '../types/examInProgress.types';
import {
  remainingExamSeconds,
  type ExamClockSync,
} from '../utils/examClock';

type Filter = 'presentes' | 'retraso' | 'ausentes' | 'expulsados' | 'todos';

const CAMPUS_TIME_ZONE = 'America/La_Paz';

function clock(seconds: number): string {
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const remainingSeconds = seconds % 60;

  return [hours, minutes, remainingSeconds]
    .map((value) => String(value).padStart(2, '0'))
    .join(':');
}

function localTime(value: string): string {
  return new Intl.DateTimeFormat('es-BO', {
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZone: CAMPUS_TIME_ZONE,
  }).format(new Date(value));
}

function entryLabel(student: InProgressStudent): string {
  if (student.expulsado_en) return `Expulsado · ${localTime(student.expulsado_en)}`;
  if (student.estado_ingreso === 'INGRESO') return 'A tiempo';
  if (student.estado_ingreso === 'CON_RETRASO') return 'Con retraso';
  return 'Ausente';
}

interface ExamInProgressPageProps {
  extensions?: ExamInProgressExtensions;
}

export function ExamInProgressPage({ extensions }: ExamInProgressPageProps) {
  const { examId } = useParams<{ examId: string }>();
  const id = Number(examId);

  const [data, setData] = useState<ExamInProgress | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [retry, setRetry] = useState(0);
  const [monoNow, setMonoNow] = useState(() => performance.now());
  const [clockSync, setClockSync] = useState<ExamClockSync | null>(null);
  const [filter, setFilter] = useState<Filter>('presentes');
  const [search, setSearch] = useState('');

  useEffect(() => {
    const timer = window.setInterval(() => {
      setMonoNow(performance.now());
    }, 1000);

    return () => window.clearInterval(timer);
  }, []);

  useEffect(() => {
    if (!Number.isInteger(id) || id <= 0) {
      setError('Identificador de examen no válido.');
      setLoading(false);
      return;
    }

    const controller = new AbortController();
    let inFlight = false;

    async function load() {
      if (inFlight) return;
      inFlight = true;

      try {
        const result = await examInProgressService.get(id, controller.signal);
        const serverNowMs = Date.parse(result.hora_servidor_en);

        if (!Number.isFinite(serverNowMs)) {
          throw new Error('El servidor no devolvió una hora válida.');
        }

        if (!controller.signal.aborted) {
          const sampledMonoMs = performance.now();

          setClockSync({ serverNowMs, sampledMonoMs });
          setMonoNow(sampledMonoMs);
          setData(result);
          setError(null);
        }
      } catch (cause) {
        if (!controller.signal.aborted) {
          setError(
            cause instanceof Error
              ? cause.message
              : 'No se pudo consultar el examen.'
          );
        }
      } finally {
        inFlight = false;
        if (!controller.signal.aborted) setLoading(false);
      }
    }

    void load();

    const timer = window.setInterval(() => {
      if (!document.hidden) void load();
    }, 2000);

    return () => {
      controller.abort();
      window.clearInterval(timer);
    };
  }, [id, retry]);

  const remaining =
    data?.estado === 'EN_CURSO' && clockSync
      ? remainingExamSeconds(data.fin_programado_en, clockSync, monoNow)
      : 0;

  const filterOptions: { value: Filter; label: string; count: number }[] = [
    {
      value: 'presentes',
      label: 'Presentes',
      count: data?.totales.presentes ?? 0,
    },
    {
      value: 'retraso',
      label: 'Con retraso',
      count: data?.totales.con_retraso ?? 0,
    },
    {
      value: 'ausentes',
      label: 'Ausentes',
      count: data?.totales.ausentes ?? 0,
    },
    {
      value: 'expulsados',
      label: 'Expulsados',
      count: data?.totales.expulsados ?? 0,
    },
    {
      value: 'todos',
      label: 'Todos',
      count: data?.totales.total ?? 0,
    },
  ];

  const query = normalizeSearch(search);

  const students = (data?.estudiantes ?? []).filter((student) => {
    const matchesStatus =
      filter === 'todos' ||
      (filter === 'expulsados' && !!student.expulsado_en) ||
      (filter === 'presentes' && !student.expulsado_en && student.estado_ingreso !== 'NO_INGRESO') ||
      (filter === 'retraso' && !student.expulsado_en && student.estado_ingreso === 'CON_RETRASO') ||
      (filter === 'ausentes' && !student.expulsado_en && student.estado_ingreso === 'NO_INGRESO');

    const matchesSearch = normalizeSearch([
      student.nombre_completo,
      student.cod_sis,
      student.ci ?? '',
    ].join(' ')).includes(query);

    return matchesStatus && matchesSearch;
  });

  const emptyMessage = search.trim()
    ? 'No hay estudiantes que coincidan con la búsqueda.'
    : filter === 'presentes'
      ? 'Todavía no hay estudiantes presentes.'
      : filter === 'retraso'
        ? 'No hay estudiantes con retraso.'
        : filter === 'ausentes'
          ? 'No hay estudiantes ausentes.'
          : filter === 'expulsados'
            ? 'No hay estudiantes expulsados.'
            : 'No hay estudiantes registrados en la nómina.';

  return (
    <AppShell
      mobileTitle="Examen en curso"
      breadcrumbs={[{ label: 'Examen en curso' }]}
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link
            to="/examenes/en-curso"
            className="text-sm text-muted-foreground hover:text-brand-deep"
          >
            Volver a exámenes
          </Link>
          <h1 className="sciem-h1 mt-1">Examen en curso</h1>
        </div>

        {error && (
          <Button
            variant="outline"
            onClick={() => setRetry((value) => value + 1)}
          >
            Reintentar
          </Button>
        )}
      </div>

      {loading && !data && (
        <p role="status" className="rounded-xl border bg-card p-5">
          Cargando examen…
        </p>
      )}

      {error && (
        <div
          role="alert"
          className="rounded-xl border border-warn-border bg-warn-soft p-4 text-sm"
        >
          {data
            ? `Datos sin actualizar: ${error}`
            : `No se pudo abrir el examen: ${error}`}
        </div>
      )}

      {data && (
        <div className="space-y-5">
          <section className="rounded-xl border bg-card p-5 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="text-xl font-semibold">{data.nombre_examen}</h2>
                <p className="text-sm text-muted-foreground">
                  {data.materia ?? 'Examen'}
                </p>
              </div>

              <div className="flex flex-wrap items-center gap-2">
                <span className="rounded-full bg-brand-soft px-3 py-1 text-xs font-semibold text-brand-deep">
                  {data.estado === 'FINALIZADO' ? 'Finalizado' : 'En curso'}
                </span>
                {/* HU-037 y HU-038-B: acciones del encabezado */}
                {extensions?.headerActions?.(data)}
              </div>
            </div>

            <div className="mt-5 grid gap-3 sm:grid-cols-2">
              <div className="rounded-lg bg-sunken p-4">
                <p className="text-sm text-muted-foreground">
                  Tiempo restante
                </p>
                <p
                  className="sciem-tnum mt-1 text-3xl font-semibold"
                  aria-label="Tiempo restante del examen"
                >
                  {data.estado === 'EN_CURSO' && !clockSync
                    ? 'Sincronizando…'
                    : clock(remaining)}
                </p>
                <p className="mt-2 text-xs text-muted-foreground">
                  Fin programado: {localTime(data.fin_programado_en)}
                </p>
              </div>

              <div className="rounded-lg bg-sunken p-4">
                <p className="text-sm text-muted-foreground">
                  Inicio del examen
                </p>
                <p className="mt-1 text-lg font-semibold">
                  {data.inicio_real_en
                    ? localTime(data.inicio_real_en)
                    : 'No registrado'}
                </p>

                {data.inicio_automatico && (
                  <p className="mt-2 text-sm text-brand-deep">
                    Inicio automático
                  </p>
                )}

                {data.estado === 'FINALIZADO' && (
                  <p className="mt-2 text-sm">
                    {data.fin_automatico
                      ? 'Cierre automático'
                      : 'Examen finalizado'}
                  </p>
                )}
              </div>
            </div>

            {data.estado === 'EN_CURSO' && remaining === 0 && (
              <p
                role="status"
                className="mt-3 rounded-lg bg-warn-soft p-3 font-medium text-warn-fg"
              >
                Tiempo cumplido
              </p>
            )}

          </section>

          <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {([
              ['A tiempo', data.totales.a_tiempo],
              ['Con retraso', data.totales.con_retraso],
              ['Ausentes', data.totales.ausentes],
              ['Expulsados', data.totales.expulsados ?? 0],
            ] as const).map(([label, value]) => (
              <div
                key={label}
                className="rounded-xl border bg-card p-4 shadow-sm"
              >
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className="sciem-tnum text-3xl font-semibold">{value}</p>
              </div>
            ))}
          </section>

          <section className="rounded-xl border bg-card p-4 shadow-sm">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
              <div>
                <h3 className="font-semibold">Estudiantes</h3>
                <p className="text-xs text-muted-foreground">
                  {students.length} resultados ·{' '}
                  {data.rol_controlador === 'AUXILIAR'
                    ? 'Solo su ambiente'
                    : 'Todos los ambientes'}
                </p>
              </div>

              <input
                aria-label="Buscar estudiante"
                className="h-9 w-full rounded-lg border bg-background px-3 text-sm sm:w-56"
                placeholder="Nombre, SIS o CI"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
              />
            </div>

            <div
              role="group"
              aria-label="Filtrar estudiantes por estado de ingreso"
              className="mb-4 flex max-w-full gap-2 overflow-x-auto pb-2"
            >
              {filterOptions.map((option) => {
                const selected = filter === option.value;

                return (
                  <button
                    key={option.value}
                    type="button"
                    aria-pressed={selected}
                    onClick={() => setFilter(option.value)}
                    className={[
                      'shrink-0 rounded-lg border px-3 py-2 text-sm font-medium transition-colors',
                      selected
                        ? 'border-brand-deep bg-brand-deep text-white'
                        : 'bg-background text-foreground hover:bg-sunken',
                    ].join(' ')}
                  >
                    {option.label}
                    <span className="ml-2 opacity-75">({option.count})</span>
                  </button>
                );
              })}
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="border-b text-muted-foreground">
                  <tr>
                    <th className="px-3 py-3">Código SIS</th>
                    <th className="px-3 py-3">Estudiante</th>
                    <th className="px-3 py-3">Ambiente</th>
                    <th className="px-3 py-3">Hora ingreso</th>
                    <th className="px-3 py-3">Estado</th>
                    {extensions?.studentActions && (
                      <th className="px-3 py-3">Acciones</th>
                    )}
                  </tr>
                </thead>

                <tbody>
                  {students.map((student) => (
                    <tr
                      key={student.id_estudiante}
                      className="border-b last:border-0"
                    >
                      <td className="px-3 py-3">{student.cod_sis}</td>
                      <td className="px-3 py-3">{student.nombre_completo}</td>
                      <td className="px-3 py-3">{student.ambiente ?? '—'}</td>
                      <td className="px-3 py-3">{student.hora_ingreso ?? '—'}</td>
                      <td className="px-3 py-3">{entryLabel(student)}</td>
                      {extensions?.studentActions && (
                        <td className="px-3 py-3">
                          {/* HU-042 y HU-057: acciones por estudiante */}
                          {extensions.studentActions(student, data)}
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {students.length === 0 && (
              <p className="py-6 text-center text-sm text-muted-foreground">
                {emptyMessage}
              </p>
            )}
          </section>

          {/* HU-037, HU-042 y HU-057: paneles y notificaciones */}
          {extensions?.panels && (
            <section aria-label="Funciones adicionales del examen">
              {extensions.panels(data)}
            </section>
          )}
        </div>
      )}
    </AppShell>
  );
}