import { act, renderHook } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { makeLateEntryRequest, makeLateEntryVerification } from '@/test/lateEntryFixtures'
import { LATE_ENTRY_MESSAGES } from '../components/lateEntryMessages'
import { lateEntryService } from '../services/lateEntryService'
import { useLateEntryVerification } from './useLateEntryVerification'

afterEach(() => {
  vi.restoreAllMocks()
})

function mount(roomId: number | null = null, onConflict = vi.fn()) {
  return renderHook(() => useLateEntryVerification(7, roomId, onConflict))
}

async function verifyStudent(hook: ReturnType<typeof mount>) {
  act(() => {
    hook.result.current.changeSis('2023-01903x')
    hook.result.current.changeCi('9342145')
  })
  await act(async () => { await hook.result.current.verify() })
}

describe('useLateEntryVerification', () => {
  it('exige un SIS de 9 dígitos y solo acepta dígitos', async () => {
    const verify = vi.spyOn(lateEntryService, 'verify')
    const hook = mount()

    act(() => hook.result.current.changeCi('12a3'))
    expect(hook.result.current.ci).toBe('123')

    await act(async () => { await hook.result.current.verify() })
    expect(hook.result.current.error).toBe(LATE_ENTRY_MESSAGES.sisRequired)
    expect(verify).not.toHaveBeenCalled()
  })

  it('el auxiliar verifica y solicita desde su ambiente', async () => {
    const verify = vi.spyOn(lateEntryService, 'verify').mockResolvedValue(makeLateEntryVerification())
    const request = vi.spyOn(lateEntryService, 'request').mockResolvedValue(makeLateEntryRequest())
    const hook = mount(6)

    await verifyStudent(hook)
    expect(verify).toHaveBeenCalledWith(7, { cod_sis: '202301903', ci: '9342145', id_ambiente: 6 })
    expect(hook.result.current.result?.autorizado).toBe(true)

    await act(async () => { await hook.result.current.request() })
    expect(request).toHaveBeenCalledWith(7, { id_estudiante: 501, id_ambiente: 6, ci: '9342145' })
    expect(hook.result.current.sentRequest?.hora_solicitud).toBe('10:12')
  })

  it('el docente verifica sin ambiente y registra en el que elige', async () => {
    const verify = vi.spyOn(lateEntryService, 'verify').mockResolvedValue(makeLateEntryVerification())
    const register = vi.spyOn(lateEntryService, 'register').mockResolvedValue({
      id_estudiante: 501, estado_ingreso: 'CON_RETRASO', hora_ingreso: '10:30', id_ambiente: 5,
    })
    const hook = mount()

    await verifyStudent(hook)
    expect(verify).toHaveBeenCalledWith(7, { cod_sis: '202301903', ci: '9342145' })

    await act(async () => { await hook.result.current.register(5) })
    expect(register).toHaveBeenCalledWith(7, { id_estudiante: 501, id_ambiente: 5, ci: '9342145' })
    expect(hook.result.current.notice).toBe(LATE_ENTRY_MESSAGES.registered('Lucía Fernández Rojas', '10:30'))
    expect(hook.result.current.result).toBeNull()
    expect(hook.result.current.sis).toBe('')
  })

  it('no escribe si el veredicto no autoriza', async () => {
    vi.spyOn(lateEntryService, 'verify').mockResolvedValue(makeLateEntryVerification({
      veredicto: 'CI_NO_COINCIDE', autorizado: false, motivo: 'El CI no coincide con el registrado',
    }))
    const register = vi.spyOn(lateEntryService, 'register')
    const hook = mount()

    await verifyStudent(hook)
    await act(async () => { await hook.result.current.register(5) })

    expect(register).not.toHaveBeenCalled()
  })

  it('un 409 al registrar muestra el mensaje, avisa y vuelve a verificar', async () => {
    const pending = makeLateEntryRequest()
    const verify = vi.spyOn(lateEntryService, 'verify')
      .mockResolvedValueOnce(makeLateEntryVerification())
      .mockResolvedValueOnce(makeLateEntryVerification({ solicitud_pendiente: pending }))
    vi.spyOn(lateEntryService, 'register').mockRejectedValue(
      new ApiError(409, 'Existe una solicitud pendiente para este estudiante. Autorícela o rechácela.')
    )
    const onConflict = vi.fn()
    const hook = mount(null, onConflict)

    await verifyStudent(hook)
    await act(async () => { await hook.result.current.register(5) })

    expect(hook.result.current.error).toBe('Existe una solicitud pendiente para este estudiante. Autorícela o rechácela.')
    expect(onConflict).toHaveBeenCalledTimes(1)
    expect(verify).toHaveBeenCalledTimes(2)
    expect(hook.result.current.result?.solicitud_pendiente).toEqual(pending)
  })

  it('un error que no es 409 solo muestra el mensaje', async () => {
    const verify = vi.spyOn(lateEntryService, 'verify').mockResolvedValue(makeLateEntryVerification())
    vi.spyOn(lateEntryService, 'request').mockRejectedValue(new ApiError(422, 'Corresponde al ambiente 691A'))
    const onConflict = vi.fn()
    const hook = mount(6, onConflict)

    await verifyStudent(hook)
    await act(async () => { await hook.result.current.request() })

    expect(hook.result.current.error).toBe('Corresponde al ambiente 691A')
    expect(onConflict).not.toHaveBeenCalled()
    expect(verify).toHaveBeenCalledTimes(1)
  })

  it('el examen cerrado al verificar avisa con onConflict', async () => {
    vi.spyOn(lateEntryService, 'verify').mockRejectedValue(
      new ApiError(409, 'Solo puede registrar ingresos con retraso mientras el examen está en curso.')
    )
    const onConflict = vi.fn()
    const hook = mount(null, onConflict)

    await verifyStudent(hook)

    expect(onConflict).toHaveBeenCalledTimes(1)
    expect(hook.result.current.result).toBeNull()
  })
})
