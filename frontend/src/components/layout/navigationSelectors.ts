import { matchPath } from 'react-router-dom'
import type { Capability } from '@/features/auth'
import { NAVIGATION, type NavItem, type NavSection } from './navigation'

export interface ActiveNavigation {
  sectionId: string
  itemId: string
}

/**
 * Las secciones que corresponden a las capacidades dadas, con solo sus entradas permitidas.
 *
 * Una sección sin la capacidad de entrada, o sin ninguna entrada permitida, no se devuelve. Sin
 * capacidades (sin sesión o rol desconocido) el menú queda vacío.
 */
export function visibleSections(
  capabilities: readonly Capability[],
  navigation: readonly NavSection[] = NAVIGATION
): NavSection[] {
  return navigation
    .filter((section) => capabilities.includes(section.capability))
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => capabilities.includes(item.capability))
    }))
    .filter((section) => section.items.length > 0)
}

/** Si la entrada lleva a una pantalla (las deshabilitadas no tienen destino). */
export function isConnected(item: NavItem): item is NavItem & { to: string } {
  return item.to !== undefined
}

/**
 * Cuán específico es un patrón de ruta: primero cuántos segmentos fijos tiene y, a igualdad,
 * cuántos comodines o parámetros (menos es más específico). `/examenes/nuevo` supera a
 * `/examenes/:examId/*` aunque ambos coincidan con la misma ruta.
 */
function specificity(pattern: string): [number, number] {
  const segments = pattern.split('/').filter(Boolean)
  const fixed = segments.filter((segment) => !segment.startsWith(':') && segment !== '*').length

  return [fixed, -(segments.length - fixed)]
}

function isMoreSpecific(candidate: [number, number], current: [number, number]): boolean {
  return candidate[0] !== current[0] ? candidate[0] > current[0] : candidate[1] > current[1]
}

/**
 * La entrada del menú a la que pertenece la ruta actual, o `null` si ninguna la reclama.
 *
 * Cada entrada con destino reclama su `to` (con sus subrutas) y sus `matches`. Si varias
 * reclaman la ruta gana el patrón más específico, y a igualdad la que se declaró primero, de modo
 * que solo una queda activa. Las entradas deshabilitadas nunca son activas. La query se ignora.
 */
export function findActiveItem(
  sections: readonly NavSection[],
  pathname: string
): ActiveNavigation | null {
  let best: { active: ActiveNavigation; score: [number, number] } | null = null

  for (const section of sections) {
    for (const item of section.items) {
      if (!isConnected(item)) continue

      for (const pattern of [item.to, ...(item.matches ?? [])]) {
        if (!matchPath({ path: pattern, end: false }, pathname)) continue

        const score = specificity(pattern)

        if (best === null || isMoreSpecific(score, best.score)) {
          best = { active: { sectionId: section.id, itemId: item.id }, score }
        }
      }
    }
  }

  return best?.active ?? null
}
