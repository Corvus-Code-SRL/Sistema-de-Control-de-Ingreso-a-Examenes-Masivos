import type { ReactNode } from 'react'

import { AppShell } from '@/components/layout/AppShell'
import { PageHeader } from '@/components/common/PageHeader'

export const ELIGIBLE_LIST_TITLE = 'Cargar lista de habilitados'

const BREADCRUMBS = [{ label: 'Preparación de exámenes' }, { label: ELIGIBLE_LIST_TITLE }]

interface EligibleListShellProps {
  /** Si se omite, la pantalla no muestra encabezado (por ejemplo mientras carga). */
  subtitle?: string
  children: ReactNode
}

/** Marco común de la carga de habilitados: barra superior, miga y encabezado de página. */
export function EligibleListShell({ subtitle, children }: EligibleListShellProps) {
  return (
    <AppShell mobileTitle={ELIGIBLE_LIST_TITLE} breadcrumbs={BREADCRUMBS}>
      {subtitle && <PageHeader title={ELIGIBLE_LIST_TITLE} subtitle={subtitle} />}
      {children}
    </AppShell>
  )
}
