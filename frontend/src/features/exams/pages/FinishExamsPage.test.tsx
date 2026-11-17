import type { ReactNode } from 'react';
import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { mockApiWith } from '@/test/http';
import { renderWithRouter } from '@/test/render';
import type { Exam } from '../types/exams.types';
import { FinishExamsPage } from './FinishExamsPage';

vi.mock('@/components/layout/AppShell', () => ({
  AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const exam: Exam = {
  id_examen: 8,
  nombre_examen: 'Parcial de Álgebra',
  estado: 'EN_CURSO',
  fecha: '2026-10-29',
  hora_inicio: '09:00',
  hora_fin: '11:00',
  duracion: 120,
  normas: null,
  id_tipo_examen: 1,
  id_carrera: 2,
  id_materia: 42,
  id_usuario_docente: 'titular',
  es_creador: true,
  materia: { id_materia: 42, nombre: 'Álgebra Lineal', codigo: 'ALG' },
  ambientes: [{ id_ambiente: 1, nro_aula: '692C', capacidad: 50 }],
};

const finishedExam: Exam = {
  ...exam,
  estado: 'FINALIZADO',
  fin_real_en: '2026-10-29T15:03:00Z',
};
const collaboratorExam: Exam = {
  ...exam,
  id_examen: 13,
  nombre_examen: 'Examen en colaboración',
  id_usuario_docente: 'otro-docente',
  es_creador: false,
};
const triggerName = `Terminar examen: ${exam.nombre_examen}`;

function renderPage() {
  return renderWithRouter(<FinishExamsPage />, {
    route: '/examenes/terminar',
    path: '/examenes/terminar',
  });
}

async function openConfirmation(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('button', { name: triggerName }));
  return within(await screen.findByRole('dialog', { name: '¿Terminar el examen?' }));
}

afterEach(() => vi.unstubAllGlobals());

describe('HU-047: Terminar examen', () => {
  it('ofrece el cierre solo para exámenes propios EN_CURSO, aunque la API incluya colaboraciones', async () => {
    const { calls } = mockApiWith(() => ({
      body: { data: [
        exam,
        { ...exam, id_examen: 9, nombre_examen: 'Siguiente examen', estado: 'PROGRAMADO' },
        { ...finishedExam, id_examen: 10, nombre_examen: 'Examen anterior' },
        { ...exam, id_examen: 11, nombre_examen: 'Examen anulado', estado: 'CANCELADO' },
        collaboratorExam,
      ] },
    }));
    renderPage();

    expect(screen.getByRole('status')).toHaveTextContent('Cargando exámenes disponibles para finalizar');
    const list = within(await screen.findByRole('list', { name: 'Exámenes disponibles para finalizar' }));
    expect(list.getAllByRole('listitem')).toHaveLength(1);
    expect(list.getByRole('button', { name: triggerName })).toBeInTheDocument();
    for (const name of ['Siguiente examen', 'Examen anterior', 'Examen anulado', collaboratorExam.nombre_examen]) {
      expect(screen.queryByText(name)).not.toBeInTheDocument();
    }
    expect(calls).toEqual([expect.objectContaining({ url: expect.stringMatching(/\/examenes$/), method: 'GET' })]);
    expect(screen.queryByRole('link', { name: /historial|reporte/i })).not.toBeInTheDocument();
  });

  it('presenta el estado vacío si ningún examen puede finalizarse', async () => {
    mockApiWith(() => ({ body: { data: [finishedExam, collaboratorExam] } }));
    renderPage();

    expect(await screen.findByText('No tiene exámenes disponibles para finalizar')).toBeInTheDocument();
    expect(screen.queryByRole('list')).not.toBeInTheDocument();
  });

  it('permite reintentar la carga tras un error', async () => {
    let attempts = 0;
    mockApiWith(() => ++attempts === 1
      ? { status: 500, body: { message: 'No se pudo obtener la lista.' } }
      : { body: { data: [exam] } });
    renderPage();

    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo cargar la información');
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }));
    expect(await screen.findByRole('button', { name: triggerName })).toBeInTheDocument();
  });

  it.each(['Seguir en curso', 'Cerrar confirmación', 'Escape'])(
    'muestra datos propios del examen y permite salir con %s sin enviar una solicitud',
    async (exit) => {
      const { calls } = mockApiWith(() => ({ body: { data: [exam] } }));
      const user = userEvent.setup();
      renderPage();

      const dialog = await openConfirmation(user);
      expect(dialog.getByRole('button', { name: 'Seguir en curso' })).toHaveFocus();
      for (const value of [exam.nombre_examen, 'Álgebra Lineal', exam.fecha, '09:00–11:00', 'En curso']) {
        expect(dialog.getByText(value, { exact: true })).toBeInTheDocument();
      }
      expect(dialog.queryByText(/estudiantes|ingresados|ausentes|asistencia|incidencias|cierre automático/i))
        .not.toBeInTheDocument();

      if (exit === 'Escape') {
        await user.keyboard('{Escape}');
      } else {
        await user.click(dialog.getByRole('button', { name: exit }));
      }
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      expect(screen.getByRole('button', { name: triggerName })).toHaveFocus();
      expect(calls.map(({ method }) => method)).toEqual(['GET']);
    }
  );

  it('envía un solo POST ante doble clic y retira el candidato después del éxito', async () => {
    let respond!: (response: Response) => void;
    const pendingResponse = new Promise<Response>((resolve) => { respond = resolve; });
    const fetch = vi.fn((input: RequestInfo | URL, init?: RequestInit) => {
      if (String(input).endsWith('/examenes/8/finalizar') && init?.method === 'POST') {
        return pendingResponse;
      }
      return Promise.resolve(Response.json({ data: [exam] }));
    });
    vi.stubGlobal('fetch', fetch);
    const user = userEvent.setup();
    renderPage();

    const dialog = await openConfirmation(user);
    await user.dblClick(dialog.getByRole('button', { name: 'Terminar examen' }));
    expect(dialog.getByRole('button', { name: 'Terminando examen…' })).toBeDisabled();
    expect(dialog.getByRole('button', { name: 'Seguir en curso' })).toBeDisabled();
    expect(fetch.mock.calls.filter(([, init]) => init?.method === 'POST')).toHaveLength(1);
    expect(dialog.getByText(exam.nombre_examen)).toBeInTheDocument();

    respond(Response.json({ data: finishedExam }));
    const success = await screen.findByRole('heading', { name: 'Examen finalizado correctamente.' });
    expect(success.closest('[tabindex="-1"]')).toHaveFocus();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: triggerName })).not.toBeInTheDocument();
    expect(screen.getByText('No tiene exámenes disponibles para finalizar')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Terminar examen', level: 1 })).toBeInTheDocument();
    expect(fetch).toHaveBeenCalledTimes(2);
  });

  it('ante un 403 de titularidad muestra un mensaje genérico en español y conserva el examen', async () => {
    mockApiWith(({ method }) => method === 'GET'
      ? { body: { data: [exam] } }
      : { status: 403, body: { message: 'Solo el docente que creó el examen puede modificarlo o cancelarlo.' } });
    const user = userEvent.setup();
    renderPage();

    const dialog = await openConfirmation(user);
    await user.click(dialog.getByRole('button', { name: 'Terminar examen' }));
    expect(await dialog.findByRole('alert')).toHaveTextContent(/^No tiene autorización para finalizar este examen$/);
    expect(screen.queryByText('Examen finalizado correctamente.')).not.toBeInTheDocument();
    await user.keyboard('{Escape}');
    expect(screen.getByRole('button', { name: triggerName })).toBeInTheDocument();
  });

  it('ante un 409 actualiza la lista y muestra que el candidato ya no está disponible', async () => {
    const anotherExam = { ...exam, id_examen: 12, nombre_examen: 'Otro parcial en curso' };
    let conflicted = false;
    const { calls } = mockApiWith(({ method }) => {
      if (method === 'POST') {
        conflicted = true;
        return { status: 409, body: { message: 'El examen ya está finalizado.' } };
      }
      return { body: { data: conflicted ? [{ ...finishedExam, fin_automatico: true }, anotherExam] : [exam] } };
    });
    const user = userEvent.setup();
    renderPage();

    const dialog = await openConfirmation(user);
    await user.click(dialog.getByRole('button', { name: 'Terminar examen' }));
    expect(await screen.findByText('El examen ya no está disponible para finalizar.')).toBeInTheDocument();
    expect(await screen.findByRole('button', { name: 'Terminar examen: Otro parcial en curso' })).toBeInTheDocument();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: triggerName })).not.toBeInTheDocument();
    expect(screen.queryByText(/automático/i)).not.toBeInTheDocument();
    expect(calls.map(({ method }) => method)).toEqual(['GET', 'POST', 'GET']);
  });
});
