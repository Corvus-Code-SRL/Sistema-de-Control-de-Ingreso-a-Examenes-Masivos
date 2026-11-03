import type { ReactNode } from 'react';
import { act, fireEvent, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '@/lib/api-client';
import { mockApiOnce } from '@/test/http';
import { renderWithRouter } from '@/test/render';
import { FinishExamAction } from '../components/FinishExamAction';
import { examInProgressService } from '../services/examInProgressService';
import { examsService } from '../services/examsService';
import type { ExamInProgress } from '../types/examInProgress.types';
import { ExamInProgressPage } from './ExamInProgressPage';

vi.mock('@/components/layout/AppShell', () => ({
  AppShell: ({ children }: { children: ReactNode }) => (
    <main>{children}</main>
  ),
}));
vi.mock('../services/examInProgressService', () => ({
  examInProgressService: { get: vi.fn() },
}));

const exam: ExamInProgress = {
  id_examen: 8,
  nombre_examen: 'Tercer Parcial',
  materia: 'Álgebra Lineal',
  estado: 'EN_CURSO',
  rol_controlador: 'DOCENTE',
  id_ambiente_asignado: null,
  ambientes: [{ id_ambiente: 1, nro_aula: '692C', capacidad: 50 }],
  inicio_real_en: '2026-10-29T13:00:00Z',
  fin_real_en: null,
  inicio_automatico: false,
  fin_automatico: false,
  fin_programado_en: '2026-10-29T15:00:00Z',
  hora_servidor_en: '2026-10-29T13:01:00Z',
  totales: {
    a_tiempo: 1,
    con_retraso: 0,
    ausentes: 1,
    presentes: 1,
    total: 2,
    incidencias_pendientes: 0,
  },
  estudiantes: [
    {
      id_estudiante: 10,
      cod_sis: '202300100',
      ci: '1234567',
      nombre_completo: 'María Pérez',
      estado_ingreso: 'INGRESO',
      hora_ingreso: '09:30',
      id_ambiente: 1,
      ambiente: '692C',
    },
    {
      id_estudiante: 11,
      cod_sis: '202300200',
      ci: '7654321',
      nombre_completo: 'Juan López',
      estado_ingreso: 'NO_INGRESO',
      hora_ingreso: null,
      id_ambiente: 1,
      ambiente: '692C',
    },
  ],
};

const hidden = Object.getOwnPropertyDescriptor(document, 'hidden');

beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(examInProgressService.get).mockResolvedValue(exam);
});

afterEach(() => {
  vi.useRealTimers();
  if (hidden) Object.defineProperty(document, 'hidden', hidden);
});

describe('HU-038-A: puntos de extensión de En curso', () => {
  it('permite inyectar los tres puntos sin modificar la página', async () => {
    const headerActions = vi.fn((value: ExamInProgress) => (
      <button>Terminar {value.id_examen}</button>
    ));
    const studentActions = vi.fn((student: ExamInProgress['estudiantes'][number]) => (
      <button>Reportar {student.cod_sis}</button>
    ));
    const panels = vi.fn((value: ExamInProgress) => (
      <p>Situaciones del examen {value.id_examen}</p>
    ));

    renderWithRouter(
      <ExamInProgressPage
        extensions={{ headerActions, studentActions, panels }}
      />,
      { route: '/examenes/8/en-curso', path: '/examenes/:examId/en-curso' }
    );

    expect(await screen.findByRole('button', { name: 'Terminar 8' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Reportar 202300100' })).toBeInTheDocument();
    expect(screen.getByText('Situaciones del examen 8')).toBeInTheDocument();
    expect(headerActions).toHaveBeenCalledWith(expect.objectContaining({ id_examen: 8 }));
    expect(studentActions).toHaveBeenCalledWith(
      expect.objectContaining({ id_estudiante: 10 }),
      expect.objectContaining({ id_examen: 8 })
    );
    expect(panels).toHaveBeenCalledWith(expect.objectContaining({ id_examen: 8 }));
    // El filtro inicial Presentes no incluye al alumno ausente.
    expect(screen.queryByRole('button', { name: 'Reportar 202300200' })).not.toBeInTheDocument();
  });

  it('no muestra celdas de acciones vacías sin extensiones', async () => {
    renderWithRouter(<ExamInProgressPage />, {
      route: '/examenes/8/en-curso',
      path: '/examenes/:examId/en-curso',
    });

    expect(await screen.findByText('María Pérez')).toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: 'Acciones' })).not.toBeInTheDocument();
    expect(screen.queryByRole('region', { name: 'Funciones adicionales del examen' })).not.toBeInTheDocument();
  });
});

describe('HU-038-A: consulta y filtros de En curso', () => {
  it('muestra el inicio automático, el fin programado y los totales', async () => {
    vi.mocked(examInProgressService.get).mockResolvedValue({
      ...exam,
      inicio_automatico: true,
    });

    renderWithRouter(<ExamInProgressPage />, {
      route: '/examenes/8/en-curso',
      path: '/examenes/:examId/en-curso',
    });

    expect(await screen.findByText('Tercer Parcial')).toBeInTheDocument();
    expect(screen.getByText('Inicio automático')).toBeInTheDocument();
    expect(screen.getByText(/Fin programado:/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Presentes (1)' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: 'Ausentes (1)' })).toBeInTheDocument();
    expect(screen.getByText('María Pérez')).toBeInTheDocument();
    expect(screen.queryByText('Juan López')).not.toBeInTheDocument();
  });

  it('filtra retrasados y ausentes y busca nombre sin tildes, SIS y CI', async () => {
    const user = userEvent.setup();
    vi.mocked(examInProgressService.get).mockResolvedValue({
      ...exam,
      totales: {
        ...exam.totales,
        con_retraso: 1,
        presentes: 2,
        total: 3,
      },
      estudiantes: [
        ...exam.estudiantes,
        {
          id_estudiante: 12,
          cod_sis: '202300300',
          ci: '9876543',
          nombre_completo: 'Álvaro Núñez',
          estado_ingreso: 'CON_RETRASO',
          hora_ingreso: '10:12',
          id_ambiente: 1,
          ambiente: '692C',
        },
      ],
    });

    renderWithRouter(<ExamInProgressPage />, {
      route: '/examenes/8/en-curso',
      path: '/examenes/:examId/en-curso',
    });

    expect(await screen.findByText('Álvaro Núñez')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Con retraso (1)' }));
    expect(screen.queryByText('María Pérez')).not.toBeInTheDocument();

    const search = screen.getByRole('textbox', { name: 'Buscar estudiante' });
    await user.type(search, 'alvaro');
    expect(screen.getByText('Álvaro Núñez')).toBeInTheDocument();
    await user.clear(search);
    await user.type(search, '202300300');
    expect(screen.getByText('Álvaro Núñez')).toBeInTheDocument();
    await user.clear(search);
    await user.type(search, '9876543');
    expect(screen.getByText('Álvaro Núñez')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Ausentes (1)' }));
    await user.clear(search);
    expect(screen.getByText('Juan López')).toBeInTheDocument();
    expect(screen.queryByText('Álvaro Núñez')).not.toBeInTheDocument();
  });

  it('con el filtro Todos, la búsqueda deja solo al estudiante que coincide', async () => {
    const user = userEvent.setup();
    vi.mocked(examInProgressService.get).mockResolvedValue({
      ...exam,
      totales: {
        ...exam.totales,
        con_retraso: 1,
        presentes: 2,
        total: 3,
      },
      estudiantes: [
        ...exam.estudiantes,
        {
          id_estudiante: 12,
          cod_sis: '202300300',
          ci: '9876543',
          nombre_completo: 'Álvaro Núñez',
          estado_ingreso: 'CON_RETRASO',
          hora_ingreso: '10:12',
          id_ambiente: 1,
          ambiente: '692C',
        },
      ],
    });

    renderWithRouter(<ExamInProgressPage />, {
      route: '/examenes/8/en-curso',
      path: '/examenes/:examId/en-curso',
    });

    expect(await screen.findByText('Álvaro Núñez')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Todos (3)' }));
    expect(screen.getByText('María Pérez')).toBeInTheDocument();
    expect(screen.getByText('Juan López')).toBeInTheDocument();
    expect(screen.getByText('Álvaro Núñez')).toBeInTheDocument();

    const search = screen.getByRole('textbox', { name: 'Buscar estudiante' });
    await user.type(search, 'alvaro');
    expect(screen.getByText('Álvaro Núñez')).toBeInTheDocument();
    expect(screen.queryByText('María Pérez')).not.toBeInTheDocument();
    expect(screen.queryByText('Juan López')).not.toBeInTheDocument();

    await user.clear(search);
    await user.type(search, '202300200');
    expect(screen.getByText('Juan López')).toBeInTheDocument();
    expect(screen.queryByText('María Pérez')).not.toBeInTheDocument();
    expect(screen.queryByText('Álvaro Núñez')).not.toBeInTheDocument();
  });
});

describe('HU-038-A: navegación de En curso', () => {
  it('«Volver a exámenes» lleva a la lista En curso', async () => {
    renderWithRouter(<ExamInProgressPage />, {
      route: '/examenes/8/en-curso',
      path: '/examenes/:examId/en-curso',
    });

    expect(await screen.findByText('María Pérez')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Volver a exámenes' })).toHaveAttribute(
      'href',
      '/examenes/en-curso'
    );
  });
});

describe('RNF-03: En curso ante un 429', () => {
  it('avisa, espera lo que pide Retry-After y sigue consultando', async () => {
    vi.useFakeTimers();
    Object.defineProperty(document, 'hidden', { configurable: true, value: false });
    const advance = (ms: number) =>
      act(async () => {
        await vi.advanceTimersByTimeAsync(ms);
      });
    const get = vi.mocked(examInProgressService.get);
    get
      .mockResolvedValueOnce(exam)
      .mockRejectedValueOnce(new ApiError(429, 'Too Many Attempts.', {}, undefined, { retryAfter: 5 }))
      .mockResolvedValue(exam);

    renderWithRouter(<ExamInProgressPage />, {
      route: '/examenes/8/en-curso',
      path: '/examenes/:examId/en-curso',
    });
    await advance(0);
    expect(get).toHaveBeenCalledTimes(1);

    await advance(2000);
    expect(get).toHaveBeenCalledTimes(2);
    expect(screen.getByRole('alert')).toHaveTextContent(
      'Datos sin actualizar: Muchas consultas seguidas, reintentando en 5 s.'
    );
    expect(screen.getByText('María Pérez')).toBeInTheDocument();

    await advance(4000);
    expect(get).toHaveBeenCalledTimes(2);

    await advance(2000);
    expect(get).toHaveBeenCalledTimes(3);
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });
});

describe('HU-038-B: terminar examen desde En curso', () => {
  const finishLabel = 'Terminar examen';
  const modalTitle = '¿Terminar el examen?';

  function renderExam() {
    return renderWithRouter(
      <ExamInProgressPage extensions={{ headerActions: (value) => <FinishExamAction exam={value} /> }} />,
      { route: '/examenes/8/en-curso', path: '/examenes/:examId/en-curso' },
    );
  }

  async function advance(ms: number) {
    await act(async () => vi.advanceTimersByTimeAsync(ms));
  }

  it('muestra los totales del examen y cierra el modal sin finalizar al cancelar', async () => {
    const user = userEvent.setup();
    const finish = vi.spyOn(examsService, 'finishExam');
    vi.mocked(examInProgressService.get).mockResolvedValue({
      ...exam,
      totales: { a_tiempo: 12, con_retraso: 3, ausentes: 5, incidencias_pendientes: 2, presentes: 15, total: 20 },
    });
    renderExam();
    await user.click(await screen.findByRole('button', { name: finishLabel }));

    const dialog = within(screen.getByRole('dialog', { name: modalTitle }));
    for (const [label, value] of [
      ['A tiempo', '12'], ['Con retraso', '3'], ['Ausentes', '5'], ['Incidencias pendientes', '2'],
    ]) {
      expect(dialog.getByText(label).parentElement).toHaveTextContent(value);
    }

    await user.click(dialog.getByRole('button', { name: 'Seguir en curso' }));
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(finish).not.toHaveBeenCalled();
  });

  it('oculta la acción a los auxiliares', async () => {
    vi.mocked(examInProgressService.get).mockResolvedValue({ ...exam, rol_controlador: 'AUXILIAR' });
    renderExam();

    expect(await screen.findByText(exam.nombre_examen)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: finishLabel })).not.toBeInTheDocument();
  });

  it('bloquea el doble envío y muestra el resultado de la confirmación', async () => {
    const user = userEvent.setup();
    let respond!: (value: Response) => void;
    const response = new Promise<Response>((resolve) => { respond = resolve; });
    const fetch = vi.spyOn(globalThis, 'fetch').mockReturnValue(response);
    renderExam();
    await user.click(await screen.findByRole('button', { name: finishLabel }));
    const dialog = within(screen.getByRole('dialog', { name: modalTitle }));
    await user.dblClick(dialog.getByRole('button', { name: finishLabel }));

    expect(dialog.getByRole('button', { name: 'Terminando examen…' })).toBeDisabled();
    expect(dialog.getByRole('button', { name: 'Seguir en curso' })).toBeDisabled();
    expect(fetch).toHaveBeenCalledTimes(1);

    respond(new Response(JSON.stringify({ data: { estado: 'FINALIZADO' } })));
    expect(await screen.findByText('Examen finalizado correctamente.')).toBeInTheDocument();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: finishLabel })).not.toBeInTheDocument();
  });

  it.each([
    [403, 'Solo un docente puede finalizar un examen.'],
    [409, 'El examen ya está finalizado.'],
  ])('muestra el error HTTP %s y permite cerrar el modal', async (status, message) => {
    const user = userEvent.setup();
    mockApiOnce({ status, body: { message } });
    renderExam();
    await user.click(await screen.findByRole('button', { name: finishLabel }));
    const dialog = within(screen.getByRole('dialog', { name: modalTitle }));
    await user.click(dialog.getByRole('button', { name: finishLabel }));

    expect(await dialog.findByRole('alert')).toHaveTextContent(message);
    await user.click(dialog.getByRole('button', { name: 'Seguir en curso' }));
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('cierra el modal si el sondeo confirma el cierre automático y muestra el resultado', async () => {
    vi.useFakeTimers();
    Object.defineProperty(document, 'hidden', { configurable: true, value: false });
    const finish = vi.spyOn(examsService, 'finishExam');
    vi.mocked(examInProgressService.get).mockResolvedValueOnce(exam).mockResolvedValue({
      ...exam,
      estado: 'FINALIZADO',
      fin_automatico: true,
      fin_real_en: '2026-10-29T15:10:00Z',
    });
    renderExam();
    await advance(0);
    fireEvent.click(screen.getByRole('button', { name: finishLabel }));
    expect(screen.getByRole('dialog', { name: modalTitle })).toBeInTheDocument();

    await advance(2000);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: finishLabel })).not.toBeInTheDocument();
    expect(screen.getByText('Finalizado')).toBeInTheDocument();
    expect(screen.getByText('Cierre automático')).toBeInTheDocument();
    expect(screen.getByText('11:10')).toHaveAttribute('datetime', '2026-10-29T15:10:00Z');
    expect(finish).not.toHaveBeenCalled();
  });

  it.each(['DOCENTE', 'AUXILIAR'] as const)('avisa a %s cuando vence el tiempo sin cerrar el examen', async (role) => {
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval', 'performance'] });
    Object.defineProperty(document, 'hidden', { configurable: true, value: true });
    vi.mocked(examInProgressService.get).mockResolvedValue({
      ...exam,
      rol_controlador: role,
      hora_servidor_en: '2026-10-29T14:59:59Z',
    });
    renderExam();
    await advance(0);
    const clock = screen.getByLabelText('Tiempo restante del examen');
    expect(clock).toHaveTextContent('00:00:01');
    expect(screen.queryByText('Tiempo cumplido')).not.toBeInTheDocument();

    await advance(1000);
    expect(clock).toHaveTextContent('00:00:00');
    expect(screen.getByText('Tiempo cumplido')).toBeInTheDocument();
    await advance(3000);
    expect(clock).toHaveTextContent('00:00:00');
    expect(screen.getByText('En curso', { exact: true })).toBeInTheDocument();
    expect(examInProgressService.get).toHaveBeenCalledTimes(1);
  });
});
