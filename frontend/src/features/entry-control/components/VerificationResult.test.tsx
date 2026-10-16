import { afterEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { VerificationResult } from './VerificationResult'
import type { Verification } from '../types/entry-control.types'

const result: Verification = {
  veredicto: 'AUTORIZADO', motivo: 'Estudiante inscrito.', autorizado: true,
  estudiante: {
    id_estudiante: 9501, cod_sis: '202150001', nombre_completo: 'Adriana Arnez Céspedes',
    ci: '7100001', ci_pendiente: false, carrera: 'Ingeniería de Sistemas', foto_url: null,
  },
  grupo: { id_grupo: 1, num_grupo: '1' }, ambiente_asignado: { id_ambiente: 5, nro_aula: '691A' },
  antecedentes: { total: 0, latest: null }, ingreso_previo: null,
}

function renderResult(photoUrl: string | null) {
  render(<VerificationResult
    result={{ ...result, estudiante: { ...result.estudiante!, foto_url: photoUrl } }}
    busy={false} onConfirm={() => {}} onReject={() => {}} onNext={() => {}}
  />)
}

function mockImage(naturalWidth: number) {
  class TestImage extends EventTarget {
    complete = true
    naturalWidth = naturalWidth
    crossOrigin: string | null = null
    referrerPolicy = ''
    src = ''
  }
  vi.stubGlobal('Image', TestImage)
}

afterEach(() => vi.unstubAllGlobals())

describe('VerificationResult fotografía', () => {
  it('muestra las iniciales cuando el estudiante no tiene foto', () => {
    renderResult(null)
    expect(screen.getByText('AA')).toBeInTheDocument()
    expect(screen.queryByText('Sin fotografía')).not.toBeInTheDocument()
    expect(screen.getByText('No ingresado')).toBeInTheDocument()
    expect(screen.getByText('Aula 691A')).toBeInTheDocument()
    expect(screen.queryByRole('img', { name: /Fotografía de/ })).not.toBeInTheDocument()
  })

  it('muestra una URL de foto independiente del proveedor', async () => {
    mockImage(320)
    const url = 'https://res.cloudinary.com/sciem/image/upload/estudiantes/9501.webp'
    renderResult(url)
    expect(await screen.findByRole('img', { name: 'Fotografía de Adriana Arnez Céspedes' }))
      .toHaveAttribute('src', url)
    expect(screen.queryByText('Sin fotografía')).not.toBeInTheDocument()
  })

  it('regresa a las iniciales si la imagen no carga', async () => {
    mockImage(0)
    renderResult('https://minio.example.invalid/estudiantes/9501.webp')
    expect(await screen.findByText('AA')).toBeInTheDocument()
    expect(screen.queryByText('Sin fotografía')).not.toBeInTheDocument()
  })
})

describe('VerificationResult CI', () => {
  it('muestra el CI coincidente, el grupo y el aula como en la ficha', () => {
    render(<VerificationResult
      result={result} presentedCi="7100001" subjectName="Cálculo I"
      busy={false} onConfirm={() => {}} onReject={() => {}} onNext={() => {}}
    />)

    expect(screen.getByText('CI').nextElementSibling).toHaveTextContent('7100001 · coincide')
    expect(screen.getByText('Grupo 1 · Cálculo I')).toBeInTheDocument()
    expect(screen.getByText('Aula 691A')).toBeInTheDocument()
  })

  it('muestra CI no coincide y no ofrece aceptar el ingreso', () => {
    render(<VerificationResult
      result={{ ...result, veredicto: 'CI_NO_COINCIDE', motivo: 'El CI presentado no coincide.', autorizado: false }}
      presentedCi="1234567" busy={false} onConfirm={() => {}} onReject={() => {}} onNext={() => {}}
    />)

    expect(screen.getByRole('heading', { name: 'CI NO COINCIDE' })).toBeInTheDocument()
    expect(screen.getByText('El CI presentado no coincide.')).toBeInTheDocument()
    expect(screen.getByText('CI').nextElementSibling).toHaveTextContent('1234567 · registrado: 7100001')
    expect(screen.queryByRole('button', { name: 'Aceptar ingreso' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Siguiente estudiante' })).toBeInTheDocument()
  })
})

describe('VerificationResult reporte de incidencia', () => {
  it('ofrece reportar un ingreso duplicado y conserva siguiente estudiante', () => {
    const onReport = vi.fn()
    render(<VerificationResult
      result={{ ...result, veredicto: 'DUPLICADO', autorizado: false }}
      busy={false} onConfirm={() => {}} onReject={() => {}} onNext={() => {}} onReport={onReport}
    />)

    fireEvent.click(screen.getByRole('button', { name: 'Reportar incidencia' }))
    expect(onReport).toHaveBeenCalledOnce()
    expect(screen.getByRole('button', { name: 'Siguiente estudiante' })).toBeInTheDocument()
  })

  it('no ofrece reportar cuando no se identificó a un estudiante', () => {
    render(<VerificationResult
      result={{ ...result, veredicto: 'NO_ENCONTRADO', autorizado: false, estudiante: null }}
      busy={false} onConfirm={() => {}} onReject={() => {}} onNext={() => {}} onReport={() => {}}
    />)
    expect(screen.queryByRole('button', { name: 'Reportar incidencia' })).not.toBeInTheDocument()
  })
})

describe('VerificationResult inscripción y riesgo', () => {
  it('muestra la ficha y la alerta de un alumno ajeno sin permitir confirmar', () => {
    render(<VerificationResult
      result={{
        ...result,
        veredicto: 'NO_PERTENECE',
        autorizado: false,
        grupo: null,
        motivo: 'No figura en los grupos de este examen.',
        antecedentes: {
          total: 1,
          latest: { tipo_falta: 'Suplantación', fecha_registro: '2026-10-13' },
        },
      }}
      busy={false} onConfirm={() => {}} onReject={() => {}} onNext={() => {}}
    />)

    expect(screen.getByText('Adriana Arnez Céspedes')).toBeInTheDocument()
    expect(screen.getByText('No inscrito en este examen')).toBeInTheDocument()
    expect(screen.getByText('Este estudiante tiene un registro en la Central de Riesgo')).toBeInTheDocument()
    expect(screen.getByText('Suplantación · 2026-10-13')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Aceptar ingreso' })).not.toBeInTheDocument()
  })

  it('muestra el total plural y el último registro sin impedir confirmar', () => {
    render(<VerificationResult
      result={{ ...result, antecedentes: {
        total: 3, latest: { tipo_falta: 'Suplantación', fecha_registro: '2026-10-14' },
      } }}
      busy={false} onConfirm={() => {}} onReject={() => {}} onNext={() => {}}
    />)

    expect(screen.getByText('Este estudiante tiene 3 registros en la Central de Riesgo')).toBeInTheDocument()
    expect(screen.getByText('Suplantación · 2026-10-14')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Aceptar ingreso' })).toBeInTheDocument()
  })

  it('identifica al inscrito no habilitado sin perder su ficha', () => {
    render(<VerificationResult
      result={{ ...result, veredicto: 'NO_HABILITADO', autorizado: false }}
      busy={false} onConfirm={() => {}} onReject={() => {}} onNext={() => {}}
    />)

    expect(screen.getByText('No habilitado para este examen')).toBeInTheDocument()
    expect(screen.getByText('Adriana Arnez Céspedes')).toBeInTheDocument()
  })
})
