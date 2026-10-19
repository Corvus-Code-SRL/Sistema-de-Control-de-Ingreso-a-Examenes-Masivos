import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { ReportIncidentDialog } from './ReportIncidentDialog'

const context = {
  examId: 7, examName: 'Primer parcial', roomId: 5, attemptId: 30,
  student: { id_estudiante: 19, cod_sis: '202309201', nombre_completo: 'Carlos Rocha' },
  roomName: '691A', reason: 'Identidad dudosa',
}

function setup(postStatus = 201, postBody: unknown = { data: { id_reporte_est: 100, id_intento: 30, estado: 'PENDIENTE' } }) {
  const { calls } = mockApiWith((request) => {
    if (request.url.endsWith('/tipos-falta')) return { body: { data: [{ id_falta: 4, nombre: 'Suplantación', descripcion: null }] } }
    if (request.url.endsWith('/intentos/30/reporte')) return { status: postStatus, body: postBody }
  })
  const onClose = vi.fn()
  const onReported = vi.fn()
  const view = render(<ReportIncidentDialog context={context} onClose={onClose} onReported={onReported} />)
  return { calls, onClose, onReported, ...view, user: userEvent.setup({ applyAccept: false }) }
}

async function complete(user: ReturnType<typeof userEvent.setup>) {
  await waitFor(() => expect(screen.getByRole('combobox')).toBeEnabled())
  await user.click(screen.getByRole('combobox'))
  await user.click(screen.getByRole('option', { name: 'Suplantación' }))
  await user.type(screen.getByRole('textbox', { name: 'Descripción' }), 'La documentación no coincide con el estudiante.')
}

const posts = (calls: { method: string }[]) => calls.filter((call) => call.method === 'POST')

describe('HU-042 ReportIncidentDialog', () => {
  it('precarga los datos del intento y consulta el catálogo real', async () => {
    const { calls } = setup()
    expect(screen.getByText('Carlos Rocha')).toBeInTheDocument()
    expect(screen.getByText('691A')).toBeInTheDocument()
    expect(screen.getByText('Primer parcial')).toBeInTheDocument()
    expect(screen.getByText('Identidad dudosa')).toBeInTheDocument()
    await waitFor(() => expect(screen.getByRole('combobox')).toBeEnabled())
    expect(calls[0].url).toMatch(/\/tipos-falta$/)
  })

  it('bloquea el envío sin tipo ni descripción válida', async () => {
    const { user, calls } = setup()
    await waitFor(() => expect(screen.getByRole('button', { name: 'Enviar a revisión' })).toBeEnabled())
    await user.click(screen.getByRole('button', { name: 'Enviar a revisión' }))
    expect(screen.getByText('Seleccione un tipo de falta.')).toBeInTheDocument()
    expect(screen.getByText('La descripción debe tener entre 10 y 500 caracteres.')).toBeInTheDocument()
    expect(posts(calls)).toHaveLength(0)
  })

  it('envía sin archivo y solo comunica éxito tras la respuesta', async () => {
    const { user, calls, onReported } = setup()
    await complete(user)
    await user.click(screen.getByRole('button', { name: 'Enviar a revisión' }))
    await waitFor(() => expect(onReported).toHaveBeenCalledWith(false))
    const request = calls.find((call) => call.method === 'POST')!
    expect(request.body).toBeInstanceOf(FormData)
    const body = request.body as FormData
    expect(body.get('id_tipo_falta')).toBe('4')
    expect(body.has('evidencia')).toBe(false)
    expect(body.has('id_estudiante')).toBe(false)
    expect(body.has('estado')).toBe(false)
    expect(request.headers['Content-Type']).toBeUndefined()
  })

  it.each(['image/jpeg', 'image/png', 'application/pdf'])('adjunta evidencia %s', async (type) => {
    const { user, calls, onReported } = setup()
    await complete(user)
    const file = new File(['evidencia'], type === 'application/pdf' ? 'prueba.pdf' : 'prueba.jpg', { type })
    await user.upload(screen.getByLabelText('Evidencia (opcional)'), file)
    await user.click(screen.getByRole('button', { name: 'Enviar a revisión' }))
    await waitFor(() => expect(onReported).toHaveBeenCalledWith(false))
    const body = calls.find((call) => call.method === 'POST')!.body as FormData
    expect(body.get('evidencia')).toBe(file)
  })

  it.each(['format', 'size'])('rechaza evidencia inválida por %s', async (kind) => {
    const { user, calls } = setup()
    await complete(user)
    const file = kind === 'format'
      ? new File(['texto'], 'prueba.txt', { type: 'text/plain' })
      : new File([new Uint8Array(5 * 1024 * 1024 + 1)], 'grande.pdf', { type: 'application/pdf' })
    await user.upload(screen.getByLabelText('Evidencia (opcional)'), file)
    await user.click(screen.getByRole('button', { name: 'Enviar a revisión' }))
    expect(screen.getByText('Formato o tamaño no permitido')).toBeInTheDocument()
    expect(posts(calls)).toHaveLength(0)
  })

  it('cancelar no envía un reporte', async () => {
    const { user, calls, onClose } = setup()
    await user.type(screen.getByRole('textbox', { name: 'Descripción' }), 'Descripción sin enviar')
    await user.click(screen.getByRole('button', { name: 'Cancelar' }))
    expect(onClose).toHaveBeenCalledOnce()
    expect(posts(calls)).toHaveLength(0)
  })

  it('interpreta un duplicado informado por el servidor', async () => {
    const { user, onReported } = setup(422, { message: 'Ya reportado', errors: { id_intento: ['Ya reportado'] } })
    await complete(user)
    await user.click(screen.getByRole('button', { name: 'Enviar a revisión' }))
    await waitFor(() => expect(onReported).toHaveBeenCalledWith(true))
  })

  it('muestra la validación del servidor junto al campo', async () => {
    const { user, onReported } = setup(422, { errors: { evidencia: ['El archivo no es válido.'] } })
    await complete(user)
    await user.click(screen.getByRole('button', { name: 'Enviar a revisión' }))
    expect(await screen.findByText('El archivo no es válido.')).toBeInTheDocument()
    expect(onReported).not.toHaveBeenCalled()
  })

  it('no expone detalles internos ante un error del servidor', async () => {
    const { user, onReported } = setup(500, { message: 'SQLSTATE datos internos' })
    await complete(user)
    await user.click(screen.getByRole('button', { name: 'Enviar a revisión' }))
    expect(await screen.findByText('No se pudo enviar el reporte. Intente nuevamente.')).toBeInTheDocument()
    expect(screen.queryByText(/SQLSTATE/)).not.toBeInTheDocument()
    expect(onReported).not.toHaveBeenCalled()
  })

  it('bloquea el envío y permite reintentar si falla el catálogo', async () => {
    let failing = true
    mockApiWith(() => failing ? { status: 500 } : { body: { data: [{ id_falta: 4, nombre: 'Suplantación' }] } })
    render(<ReportIncidentDialog context={context} onClose={vi.fn()} onReported={vi.fn()} />)
    const user = userEvent.setup()
    expect(await screen.findByText('No se pudieron cargar los tipos de falta.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Enviar a revisión' })).toBeDisabled()
    failing = false
    await user.click(screen.getByRole('button', { name: 'Reintentar carga' }))
    await waitFor(() => expect(screen.getByRole('button', { name: 'Enviar a revisión' })).toBeEnabled())
  })

  it('permite quitar la evidencia y enviar sin ella', async () => {
    const { user, calls, onReported } = setup()
    await complete(user)
    await user.upload(screen.getByLabelText('Evidencia (opcional)'), new File(['pdf'], 'prueba.pdf', { type: 'application/pdf' }))
    expect(screen.getByText('prueba.pdf')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Quitar evidencia' }))
    expect(screen.queryByText('prueba.pdf')).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Enviar a revisión' }))
    await waitFor(() => expect(onReported).toHaveBeenCalledWith(false))
    const body = calls.find((call) => call.method === 'POST')!.body as FormData
    expect(body.has('evidencia')).toBe(false)
  })

  it('la selección visual abre el input de archivo', async () => {
    const { user } = setup()
    const fileInput = screen.getByLabelText('Evidencia (opcional)') as HTMLInputElement
    const click = vi.spyOn(fileInput, 'click')
    await user.click(screen.getByRole('button', { name: 'Adjunte una foto o un PDF' }))
    expect(click).toHaveBeenCalledOnce()
  })

})
