import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { AppShell } from '@/components/layout/AppShell';
import { Button } from '@/components/ui/button';
import { normalizeSearch } from '@/lib/text';
import { examInProgressService } from '../services/examInProgressService';
import type {
  ExamInProgress,
  InProgressStudent,
} from '../types/examInProgress.types';

type Filter = 'presentes' | 'retraso' | 'ausentes' | 'todos';

const CAMPUS_TIME_ZONE = 'America/La_Paz';

function clock(seconds: number): string {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;

  return [h, m, s]
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
  if (student.estado_ingreso === 'INGRESO') return 'A tiempo';
  if (student.estado_ingreso === 'CON_RETRASO') return 'Con retraso';
  return 'Ausente';
}

export function ExamInProgressPage() {
  const { examId } = useParams<{ examId: string }>();
  const id = Number(examId);

  const [data, setData] = useState<ExamInProgress | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [retry, setRetry] = useState(0);
  const [now, setNow] = useState(Date.now());
  const [filter, setFilter] = useState<Filter>('presentes');
  const [search, setSearch] = useState('');

  useEffect(() => {
    const timer = window.setInterval(() => setNow(Date.now()), 1000);
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
        const result = await examInProgressService.get(
          id,
          controller.signal
        );

        if (!controller.signal.aborted) {
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

  const endTime = data
    ? new Date(data.fin_programado_en).getTime()
    : 0;

  const remaining = data && data.estado === 'EN_CURSO'
    ? Math.max(0, Math.ceil((endTime - now) / 1000))
    : 0;

  const query = normalizeSearch(search);

  const students = (data?.estudiantes ?? []).filter((student) => {
    const matchesStatus =
      filter === 'todos' ||
      (filter === 'presentes' &&
        student.estado_ingreso !== 'NO_INGRESO') ||
      (filter === 'retraso' &&
        student.estado_ingreso === 'CON_RETRASO') ||
      (filter === 'ausentes' &&
        student.estado_ingreso === 'NO_INGRESO');

    const matchesSearch = normalizeSearch([
      student.nombre_completo,
      student.cod_sis,
      student.ci ?? '',
    ].join(' ')).includes(query);

    return matchesStatus && matchesSearch;
  });

  return (
    <AppShell
      mobileTitle="Examen en curso"
      breadcrumbs={[{ label: 'Examen en curso' }]}
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link
            to="/control-ingreso"
            className="text-sm text-muted-foreground hover:text-brand-deep"
          >
            Volver a exámenes
          </Link>
          <h1 className="sciem-h1 mt-1">Examen en curso</h1>
        </div>

        <Button
          variant="outline"
          onClick={() => setRetry((value) => value + 1)}
        >
          Actualizar
        </Button>
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
                <h2 className="text-xl font-semibold">
                  {data.nombre_examen}
                </h2>
                <p className="text-sm text-muted-foreground">
                  {data.materia ?? 'Examen'}
                </p>
              </div>

              <span className="rounded-full bg-brand-soft px-3 py-1 text-xs font-semibold text-brand-deep">
                {data.estado === 'FINALIZADO'
                  ? 'Finalizado'
                  : 'En curso'}
              </span>
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
                  {clock(remaining)}
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

            {/* HU-037 y HU-038-B: acciones del encabezado */}
            <div data-hu-extension="acciones-encabezado" />
          </section>

          <section className="grid gap-3 sm:grid-cols-3">
            {[
              ['A tiempo', data.totales.a_tiempo],
              ['Con retraso', data.totales.con_retraso],
              ['Ausentes', data.totales.ausentes],
            ].map(([label, value]) => (
              <div
                key={label}
                className="rounded-xl border bg-card p-4 shadow-sm"
              >
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className="sciem-tnum text-3xl font-semibold">
                  {value}
                </p>
              </div>
            ))}
          </section>

          <section className="rounded-xl border bg-card p-4 shadow-sm">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
              <div>
                <h3 className="font-semibold">Estudiantes</h3>
                <p className="text-xs text-muted-foreground">
                  {students.length} resultados ·
                  {data.rol_controlador === 'AUXILIAR'
                    ? ' Solo su ambiente'
                    : ' Todos los ambientes'}
                </p>
              </div>

              <div className="flex flex-wrap gap-2">
                <input
                  aria-label="Buscar estudiante"
                  className="h-9 rounded-lg border bg-background px-3 text-sm"
                  placeholder="Nombre, SIS o CI"
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                />

                <select
                  aria-label="Filtrar estudiantes"
                  className="h-9 rounded-lg border bg-background px-3 text-sm"
                  value={filter}
                  onChange={(event) =>
                    setFilter(event.target.value as Filter)
                  }
                >
                  <option value="presentes">Presentes</option>
                  <option value="retraso">Con retraso</option>
                  <option value="ausentes">Ausentes</option>
                  <option value="todos">Todos</option>
                </select>
              </div>
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
                    <th className="px-3 py-3">Acciones</th>
                  </tr>
                </thead>

                <tbody>
                  {students.map((student) => (
                    <tr
                      key={student.id_estudiante}
                      className="border-b last:border-0"
                    >
                      <td className="px-3 py-3">{student.cod_sis}</td>
                      <td className="px-3 py-3">
                        {student.nombre_completo}
                      </td>
                      <td className="px-3 py-3">
                        {student.ambiente ?? '—'}
                      </td>
                      <td className="px-3 py-3">
                        {student.hora_ingreso ?? '—'}
                      </td>
                      <td className="px-3 py-3">
                        {entryLabel(student)}
                      </td>
                      <td className="px-3 py-3">
                        {/* HU-042 y HU-057: acciones por estudiante */}
                        <div
                          data-hu-extension="acciones-estudiante"
                          data-student-id={student.id_estudiante}
                        />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {students.length === 0 && (
              <p className="py-6 text-center text-sm text-muted-foreground">
                No hay estudiantes que coincidan con los filtros.
              </p>
            )}
          </section>

          {/* HU-037, HU-042 y HU-057: paneles y notificaciones */}
          <section data-hu-extension="paneles-en-curso" />
        </div>
      )}
    </AppShell>
  );
}
