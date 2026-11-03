import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import type { ExamInProgress } from '@/features/exams'
import { renderWithRouter } from '@/test/render'
import { mockApiWith } from '@/test/http'
import { examInProgressExtensions } from './examInProgressExtensions'

const exam: ExamInProgress = {
  id_examen: 7, nombre_examen: 'Parcial', materia: 'Programación', estado: 'EN_CURSO',
  rol_controlador: 'DOCENTE', id_ambiente_asignado: null,
  ambientes: [{ id_ambiente: 5, nro_aula: '691A', capacidad: 100 }],
  inicio_real_en: '2026-10-30T12:00:00Z', fin_real_en: null, inicio_automatico: false, fin_automatico: false,
  fin_programado_en: '2026-10-30T14:00:00Z', hora_servidor_en: '2026-10-30T12:30:00Z',
  totales: { a_tiempo: 1, con_retraso: 0, ausentes: 0, presentes: 1, total: 1, incidencias_pendientes: 2, expulsados: 0 },
  incidencias_habilitadas: true,
  situaciones_habilitadas: true,
  estudiantes: [{
    id_estudiante: 19, cod_sis: '202309201', ci: '7654321', nombre_completo: 'Carlos Rocha',
    estado_ingreso: 'INGRESO', hora_ingreso: '08:00', id_ambiente: 5, ambiente: '691A',
    expulsado_en: null, total_incidencias: 2,
  }],
}

describe('composición de En curso (HU-037, HU-038-B, HU-042 y HU-057)', () => {
  it('conserva el cierre de HU-038, las situaciones de HU-057 y suma el contador y «Reportar» de HU-042', async () => {
    mockApiWith(({ url }) => {
      if (url.includes('/situaciones')) {
        return { body: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } } }
      }
      return undefined
    })

    renderWithRouter(<>
      {examInProgressExtensions.headerActions?.(exam)}
      {examInProgressExtensions.studentActions?.(exam.estudiantes[0], exam)}
      {examInProgressExtensions.panels?.(exam)}
    </>)

    expect(screen.getByRole('button', { name: /Terminar examen/ })).toBeInTheDocument()
    expect(screen.getByText('Incidencias pendientes: 2')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Registrar situación' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reportar' })).toBeInTheDocument()
    expect(await screen.findByRole('region', { name: 'Situaciones del examen' })).toBeInTheDocument()
  })
})
