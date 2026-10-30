import type { ReactNode } from 'react';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { renderWithRouter } from '@/test/render';
import { examInProgressService } from '../services/examInProgressService';
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

beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(examInProgressService.get).mockResolvedValue(exam);
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
