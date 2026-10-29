import type { ReactNode } from 'react';
import { screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useCurrentUser } from '@/features/auth';
import { assistantsService } from '@/features/assistants/services/assistantsService';
import { renderWithRouter } from '@/test/render';

import { examsService } from '../services/examsService';
import { InProgressExamsPage } from './InProgressExamsPage';

vi.mock('@/features/auth', () => ({ useCurrentUser: vi.fn() }));
vi.mock('@/components/layout/AppShell', () => ({
  AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));
vi.mock('../services/examsService', () => ({
  examsService: { listExams: vi.fn() },
}));
vi.mock('@/features/assistants/services/assistantsService', () => ({
  assistantsService: { listMyExams: vi.fn() },
}));

function renderPage() {
  return renderWithRouter(<InProgressExamsPage />, {
    route: '/examenes/en-curso',
    path: '/examenes/en-curso',
  });
}

beforeEach(() => {
  vi.clearAllMocks();
});

describe('HU-038-A: listado En curso', () => {
  it('el docente solo ve sus exámenes EN_CURSO y puede abrirlos', async () => {
    vi.mocked(useCurrentUser).mockReturnValue({ area: 'docente' } as ReturnType<typeof useCurrentUser>);
    vi.mocked(examsService.listExams).mockResolvedValue([
      {
        id_examen: 8,
        nombre_examen: 'Tercer Parcial',
        estado: 'EN_CURSO',
        fecha: '2026-10-29',
        hora_inicio: '10:00',
        hora_fin: '12:00',
        duracion: 120,
        normas: null,
        id_tipo_examen: 1,
        id_carrera: 1,
        id_materia: 1,
        id_usuario_docente: 'docente',
        materia: { id_materia: 1, nombre: 'Álgebra Lineal', codigo: 'MAT101' },
        ambientes: [{ id_ambiente: 1, nro_aula: '692C', capacidad: 50 }],
      },
      {
        id_examen: 9,
        nombre_examen: 'Examen futuro',
        estado: 'PROGRAMADO',
        fecha: '2026-11-01',
        hora_inicio: '10:00',
        hora_fin: '11:00',
        duracion: 60,
        normas: null,
        id_tipo_examen: 1,
        id_carrera: 1,
        id_materia: 1,
        id_usuario_docente: 'docente',
      },
    ]);

    renderPage();

    expect(await screen.findByText('Tercer Parcial')).toBeInTheDocument();
    expect(screen.queryByText('Examen futuro')).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ver examen' })).toHaveAttribute(
      'href',
      '/examenes/8/en-curso'
    );
    expect(examsService.listExams).toHaveBeenCalledTimes(1);
    expect(assistantsService.listMyExams).not.toHaveBeenCalled();
  });

  it('el auxiliar solo ve los exámenes EN_CURSO que le devuelve su API', async () => {
    vi.mocked(useCurrentUser).mockReturnValue({ area: 'auxiliar' } as ReturnType<typeof useCurrentUser>);
    vi.mocked(assistantsService.listMyExams).mockResolvedValue([
      {
        id_examen: 8,
        nombre_examen: 'Tercer Parcial',
        fecha: '2026-10-29',
        hora_inicio: '10:00',
        hora_fin: '12:00',
        estado: 'EN_CURSO',
        materia: 'Álgebra Lineal',
        ambiente: { id_ambiente: 1, nro_aula: '692C', capacidad: 50 },
        ambiente_por_defecto: false,
      },
      {
        id_examen: 10,
        nombre_examen: 'Próxima evaluación',
        fecha: '2026-11-02',
        hora_inicio: '09:00',
        hora_fin: '10:00',
        estado: 'PROGRAMADO',
        materia: 'Física',
        ambiente: null,
        ambiente_por_defecto: false,
      },
      {
        id_examen: 11,
        nombre_examen: 'Examen sin ambiente',
        fecha: '2026-10-29',
        hora_inicio: '10:00',
        hora_fin: '12:00',
        estado: 'EN_CURSO',
        materia: 'Química',
        ambiente: null,
        ambiente_por_defecto: false,
      },
    ]);

    renderPage();

    expect(await screen.findByText('Tercer Parcial')).toBeInTheDocument();
    expect(screen.getByText('Su ambiente: 692C')).toBeInTheDocument();
    expect(screen.queryByText('Próxima evaluación')).not.toBeInTheDocument();
    expect(screen.queryByText('Examen sin ambiente')).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ver examen' })).toHaveAttribute(
      'href',
      '/examenes/8/en-curso'
    );
    expect(assistantsService.listMyExams).toHaveBeenCalledTimes(1);
    expect(examsService.listExams).not.toHaveBeenCalled();
  });

  it('muestra el estado vacío cuando no hay exámenes activos', async () => {
    vi.mocked(useCurrentUser).mockReturnValue({ area: 'docente' } as ReturnType<typeof useCurrentUser>);
    vi.mocked(examsService.listExams).mockResolvedValue([]);

    renderPage();

    expect(await screen.findByText('No tiene exámenes en curso')).toBeInTheDocument();
  });
});
