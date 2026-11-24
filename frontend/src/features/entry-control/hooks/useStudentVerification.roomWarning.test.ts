import { act, renderHook } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { entryControlService } from '../services/entryControlService'
import type { Verification } from '../types/entry-control.types'
import { ROOM_WARNING_VERDICT } from '../utils/entryRoomWarning'
import { useStudentVerification } from './useStudentVerification'

const verdict: Verification = {
  veredicto: 'AUTORIZADO', motivo: 'Inscrito en un grupo del examen.', autorizado: true,
  estudiante: { id_estudiante: 7, cod_sis: '202400001', ci: null, ci_pendiente: false, nombre_completo: 'Ana Rojas', carrera: null, foto_url: null },
  grupo: { id_grupo: 1, num_grupo: '1' }, ambiente_asignado: null, antecedentes: { total: 0, latest: null }, ingreso_previo: null,
}

describe('useStudentVerification con aviso de ambiente', () => {
  it('pide confirmar y reenvía con la marca de confirmación', async () => {
    vi.spyOn(entryControlService, 'verify').mockResolvedValue(verdict)
    const confirm = vi.spyOn(entryControlService, 'confirm')
      .mockRejectedValueOnce(new ApiError(409, 'Confirme', {}, undefined, {
        responseBody: {
          veredicto: ROOM_WARNING_VERDICT,
          avisos_ambiente: [{ tipo: 'FUERA_DE_AMBIENTE', mensaje: 'Este estudiante tiene asignado el ambiente 691A' }],
        },
      }))
      .mockResolvedValueOnce(undefined)
    const { result } = renderHook(() => useStudentVerification(5, 20, vi.fn()))

    act(() => result.current.changeSis('202400001'))
    await act(async () => { await result.current.verify() })
    await act(async () => { await result.current.confirm() })

    expect(result.current.roomWarning.warnings).toHaveLength(1)
    expect(result.current.error).toBeNull()
    expect(confirm).toHaveBeenLastCalledWith(5, { id_estudiante: 7, id_ambiente: 20 })

    await act(async () => { result.current.roomWarning.confirm() })

    expect(confirm).toHaveBeenLastCalledWith(5, { id_estudiante: 7, id_ambiente: 20, confirmar_aviso_ambiente: true })
    expect(result.current.notice).toContain('Ingreso registrado')
  })
})
