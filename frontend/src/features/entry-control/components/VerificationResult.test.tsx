import { afterEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { VerificationResult } from './VerificationResult'
import type { Verification } from '../types/entry-control.types'

const result: Verification = {
  veredicto: 'AUTORIZADO', motivo: 'Estudiante inscrito.', autorizado: true,
  estudiante: {
    id_estudiante: 9501, cod_sis: '202150001', nombre_completo: 'Adriana Arnez Céspedes',
    ci: '7100001', ci_pendiente: false, carrera: 'Ingeniería de Sistemas', foto_url: null,
  },
  grupo: { id_grupo: 1, num_grupo: '1' }, ambiente_asignado: { id_ambiente: 5, nro_aula: '691A' },
  antecedentes: { tiene_antecedentes: false, cantidad: 0, resumen: null }, ingreso_previo: null,
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
  it('muestra las iniciales y Sin fotografía cuando el estudiante no tiene foto', () => {
    renderResult(null)
    expect(screen.getByText('AA')).toBeInTheDocument()
    expect(screen.getByText('Sin fotografía')).toBeInTheDocument()
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

  it('regresa a iniciales y Sin fotografía si la imagen no carga', async () => {
    mockImage(0)
    renderResult('https://minio.example.invalid/estudiantes/9501.webp')
    expect(await screen.findByText('Sin fotografía')).toBeInTheDocument()
    expect(screen.getByText('AA')).toBeInTheDocument()
  })
})

describe('VerificationResult CI', () => {
  it('muestra CI no coincide y no ofrece aceptar el ingreso', () => {
    render(<VerificationResult
      result={{ ...result, veredicto: 'CI_NO_COINCIDE', motivo: 'El CI presentado no coincide.', autorizado: false }}
      busy={false} onConfirm={() => {}} onReject={() => {}} onNext={() => {}}
    />)

    expect(screen.getByRole('heading', { name: 'CI NO COINCIDE' })).toBeInTheDocument()
    expect(screen.getByText('El CI presentado no coincide.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Aceptar ingreso' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Siguiente estudiante' })).toBeInTheDocument()
  })
})
