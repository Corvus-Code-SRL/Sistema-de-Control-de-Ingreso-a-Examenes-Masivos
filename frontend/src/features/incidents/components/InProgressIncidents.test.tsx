import type { ReactNode } from 'react'
import { act, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ExamInProgressPage, type ExamInProgress, type ExamInProgressExtensions } from '@/features/exams'
import { renderWithRouter } from '@/test/render'
import { mockApiWith } from '@/test/http'
import { InProgressIncidentAction, InProgressIncidentsCount } from './InProgressIncidents'
import { InProgressSituationAction } from './InProgressSituations'

vi.mock('@/components/layout/AppShell', () => ({
  AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}))
const extensions: ExamInProgressExtensions = {
  headerActions: (exam) => <InProgressIncidentsCount exam={exam} />,
  studentActions: (student, exam) => <>
    <InProgressIncidentAction student={student} exam={exam} />
    <InProgressSituationAction student={student} exam={exam} />
  </>,
}
const fixture: ExamInProgress = {
  id_examen: 7, nombre_examen: 'Parcial', materia: 'Programación', estado: 'EN_CURSO',
  rol_controlador: 'DOCENTE', id_ambiente_asignado: null,
  ambientes: [{ id_ambiente: 5, nro_aula: '691A', capacidad: 100 }, { id_ambiente: 6, nro_aula: '691B', capacidad: 100 }],
  inicio_real_en: '2026-10-30T12:00:00Z', fin_real_en: null, inicio_automatico: false, fin_automatico: false,
  fin_programado_en: '2026-10-30T14:00:00Z', hora_servidor_en: '2026-10-30T12:30:00Z',
  totales: { a_tiempo: 1, con_retraso: 0, ausentes: 1, presentes: 1, total: 2, incidencias_pendientes: 0, expulsados: 0 },
  incidencias_habilitadas: true,
  estudiantes: [
    { id_estudiante: 19, cod_sis: '202309201', ci: '1234567', nombre_completo: 'Carlos Rocha',
      estado_ingreso: 'INGRESO', hora_ingreso: '08:00', id_ambiente: 5, ambiente: '691A',
      expulsado_en: null, total_incidencias: 0 },
    { id_estudiante: 20, cod_sis: '202309202', ci: '1234568', nombre_completo: 'Ana Ausente',
      estado_ingreso: 'NO_INGRESO', hora_ingreso: null, id_ambiente: 5, ambiente: '691A' },
  ],
}
let exam: ExamInProgress
let postStatus: number
beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] })
  exam = structuredClone(fixture)
  postStatus = 201
})
afterEach(() => vi.useRealTimers())

function setup() {
  const requests = mockApiWith(({ url, method, body }) => {
    if (url.endsWith('/examenes/7/en-curso')) return { body: { data: structuredClone(exam) } }
    if (url.endsWith('/tipos-falta')) return { body: { data: [
      { id_falta: 83, nombre: 'Material no permitido', descripcion: 'Dispositivos no autorizados.' },
      { id_falta: 91, nombre: 'Copia en examen', descripcion: 'Copia de respuestas.' },
    ] } }
    if (method === 'POST' && url.endsWith('/examenes/7/estudiantes/19/incidencias')) {
      if (postStatus !== 201) return { status: postStatus, body: { message: 'El examen ya finalizó.' } }
      const data = body as FormData
      exam.estudiantes[0].total_incidencias = (exam.estudiantes[0].total_incidencias ?? 0) + 1
      exam.totales.incidencias_pendientes++
      if (data.get('retirar') === '1') {
        exam.estudiantes[0].expulsado_en = '2026-10-30T12:40:00Z'
        exam.totales.presentes = 0
        exam.totales.a_tiempo = 0
        exam.totales.expulsados = 1
      }
      return { status: 201, body: { data: { id_reporte_est: 100, id_intento: null, estado: 'PENDIENTE' } } }
    }
  })
  renderWithRouter(<ExamInProgressPage extensions={extensions} />, {
    route: '/examenes/7/en-curso', path: '/examenes/:examId/en-curso',
  })
  return { ...requests, user: userEvent.setup({ applyAccept: false }) }
}
async function poll() {
  await act(async () => { await vi.advanceTimersByTimeAsync(2000) })
}
async function open(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('button', { name: 'Reportar' }))
  return screen.findByRole('dialog', { name: 'Reportar incidencia' })
}
async function fill(user: ReturnType<typeof userEvent.setup>, dialog: HTMLElement) {
  await waitFor(() => expect(within(dialog).getByRole('combobox')).toBeEnabled())
  await user.click(within(dialog).getByRole('combobox'))
  await user.click(screen.getByRole('option', { name: 'Material no permitido' }))
  await user.type(within(dialog).getByRole('textbox', { name: 'Descripción' }), 'Usó un celular no autorizado durante el examen.')
}

describe('HU-042 durante el examen en la página de HU-038-A', () => {
  it('carga contexto reciente, catálogo real y envía sin inventar intento ni datos protegidos', async () => {
    const { user, calls } = setup()
    await screen.findByRole('button', { name: 'Reportar' })
    exam.hora_servidor_en = '2026-10-30T12:31:00Z'
    const dialog = await open(user)
    expect(within(dialog).getByText(/08:31/)).toBeInTheDocument()
    expect(within(dialog).queryByText('Motivo del rechazo')).not.toBeInTheDocument()
    await fill(user, dialog)
    expect(within(dialog).getByText('Dispositivos no autorizados.')).toBeInTheDocument()
    await user.click(within(dialog).getByRole('button', { name: 'Enviar a revisión' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(screen.getByText('Enviado a revisión del docente')).toBeInTheDocument()
    expect(screen.getByText('Incidencia reportada')).toBeInTheDocument()
    const post = calls.find((call) => call.method === 'POST')!
    expect(Object.fromEntries((post.body as FormData).entries())).toEqual({
      id_tipo_falta: '83', descripcion: 'Usó un celular no autorizado durante el examen.', id_ambiente: '5', retirar: '0',
    })
    expect(post.headers['Content-Type']).toBeUndefined()
    await poll()
    expect(screen.getByText('Incidencias pendientes: 1')).toBeInTheDocument()
    expect(within(screen.getByRole('row', { name: /Carlos Rocha/ })).getByText('08:00')).toBeInTheDocument()
  })

  it('admite otra incidencia y el polling recibe también la de otro dispositivo', async () => {
    const { user } = setup()
    for (let i = 0; i < 2; i++) {
      const dialog = await open(user)
      await fill(user, dialog)
      await user.click(within(dialog).getByRole('button', { name: 'Enviar a revisión' }))
      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
      await poll()
    }
    expect(screen.getByText('2 incidencias')).toBeInTheDocument()
    exam.estudiantes[0].total_incidencias = 3
    exam.totales.incidencias_pendientes = 3
    await poll()
    expect(screen.getByText('3 incidencias')).toBeInTheDocument()
    expect(screen.getByText('Incidencias pendientes: 3')).toBeInTheDocument()
  })

  it('retirar lo muestra en Expulsados y no permite reporte ni situación adicional', async () => {
    const { user } = setup()
    const dialog = await open(user)
    await fill(user, dialog)
    await user.click(within(dialog).getByRole('checkbox', { name: 'Retirar al estudiante del examen' }))
    await user.click(within(dialog).getByRole('button', { name: 'Enviar a revisión' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    await poll()
    expect(screen.queryByRole('row', { name: /Carlos Rocha/ })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Presentes (0)' })).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Expulsados (1)' }))
    const row = screen.getByRole('row', { name: /Carlos Rocha/ })
    expect(within(row).getByText('Expulsado · 08:40')).toBeInTheDocument()
    expect(within(row).getByText('08:00')).toBeInTheDocument()
    expect(within(row).queryByRole('button')).not.toBeInTheDocument()
  })

  it('no ofrece reportar ausentes y cancelar no escribe', async () => {
    const { user, calls } = setup()
    await user.click(await screen.findByRole('button', { name: 'Todos (2)' }))
    expect(within(screen.getByRole('row', { name: /Ana Ausente/ })).queryByRole('button')).not.toBeInTheDocument()
    const dialog = await open(user)
    await fill(user, dialog)
    await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }))
    expect(calls.filter((call) => call.method === 'POST')).toHaveLength(0)
  })

  it('un auxiliar solo puede abrir el reporte de su aula', async () => {
    exam.rol_controlador = 'AUXILIAR'
    exam.id_ambiente_asignado = 6
    setup()
    await screen.findByRole('row', { name: /Carlos Rocha/ })
    expect(screen.queryByRole('button', { name: 'Reportar' })).not.toBeInTheDocument()
  })

  it('bloquea el modal si el polling informa cierre y permite cancelarlo', async () => {
    const { user, calls } = setup()
    const dialog = await open(user)
    exam.estado = 'FINALIZADO'
    await poll()
    expect(within(dialog).getByRole('button', { name: 'Enviar a revisión' })).toBeDisabled()
    await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }))
    expect(screen.queryByRole('button', { name: 'Reportar' })).not.toBeInTheDocument()
    expect(calls.filter((call) => call.method === 'POST')).toHaveLength(0)
  })

  it('un 409 cierra la posibilidad de enviar sin anunciar éxito', async () => {
    postStatus = 409
    const { user, calls } = setup()
    const dialog = await open(user)
    await fill(user, dialog)
    await user.click(within(dialog).getByRole('button', { name: 'Enviar a revisión' }))
    expect(await screen.findByText('El examen ya finalizó.')).toBeInTheDocument()
    expect(within(dialog).getByRole('button', { name: 'Enviar a revisión' })).toBeDisabled()
    expect(screen.queryByText('Enviado a revisión del docente')).not.toBeInTheDocument()
    expect(calls.filter((call) => call.method === 'POST')).toHaveLength(1)
  })

  it('no reintenta automáticamente un envío sin confirmación', async () => {
    postStatus = 500
    const { user, calls } = setup()
    const dialog = await open(user)
    await fill(user, dialog)
    await user.click(within(dialog).getByRole('button', { name: 'Enviar a revisión' }))
    expect(await screen.findByText(/No se pudo confirmar el envío/)).toBeInTheDocument()
    expect(within(dialog).getByRole('button', { name: 'Enviar a revisión' })).toBeDisabled()
    await poll()
    expect(calls.filter((call) => call.method === 'POST')).toHaveLength(1)
  })

  it('adjunta evidencia privada con el mismo contrato multipart', async () => {
    const { user, calls } = setup()
    const dialog = await open(user)
    await fill(user, dialog)
    const file = new File(['evidencia'], 'prueba.png', { type: 'image/png' })
    await user.upload(within(dialog).getByLabelText('Evidencia (opcional)'), file)
    await user.click(within(dialog).getByRole('button', { name: 'Enviar a revisión' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect((calls.find((call) => call.method === 'POST')!.body as FormData).get('evidencia')).toBe(file)
  })

  it('informa el formato inválido con el mensaje de la guía', async () => {
    const { user, calls } = setup()
    const dialog = await open(user)
    await fill(user, dialog)
    await user.upload(within(dialog).getByLabelText('Evidencia (opcional)'), new File(['x'], 'x.txt', { type: 'text/plain' }))
    await user.click(within(dialog).getByRole('button', { name: 'Enviar a revisión' }))
    expect(screen.getByText('Formato o tamaño no permitido. Use jpg, png o pdf de hasta 5 MB.')).toBeInTheDocument()
    expect(calls.filter((call) => call.method === 'POST')).toHaveLength(0)
  })
})
