import { getSubjectCatalog } from '@/features/subjects'
import type { SubjectCareer } from '@/features/subjects'
import { ApiError } from '@/lib/api-client'
import type { Course } from '../types/group.types'
import { getGroupsByPair } from './groupsService'

/*
 * ---------------------------------------------------------------------------
 * Composición temporal
 * ---------------------------------------------------------------------------
 * La API no expone los grupos del docente en una sola consulta, así que «Mis
 * cursos» se arma cruzando el catálogo con los grupos de cada par propio.
 *
 * El reparto es acotado —solo los pares donde el docente dicta— pero sigue
 * siendo una consulta por par. Cuando exista un endpoint de cursos del docente,
 * esta función se reduce a una llamada y el hook no cambia.
 */

/** Suficientemente alto para traer el catálogo entero mientras no haya paginación real. */
const CATALOG_PAGE_SIZE = 500

/**
 * Todos los grupos que el docente dicta, sin importar carrera ni facultad.
 *
 * Un par cuyos grupos fallen no tumba la vista: se omite y el resto se muestra,
 * porque un curso inaccesible es menos grave que quedarse sin la lista entera.
 */
export async function getMyCourses(signal?: AbortSignal): Promise<Course[]> {
  const catalog = await getSubjectCatalog({ page: 1, perPage: CATALOG_PAGE_SIZE }, signal)

  const ownPairs = catalog.items.filter((pair) => pair.es_mia)

  const results = await Promise.all(
    ownPairs.map((pair) => coursesForPair(pair, signal))
  )

  return results.flat().sort(compareCourses)
}

async function coursesForPair(
  subject: SubjectCareer,
  signal?: AbortSignal
): Promise<Course[]> {
  try {
    const { groups } = await getGroupsByPair(subject.id_carrera, subject.id_materia, signal)

    return groups.filter((group) => group.es_mio).map((group) => ({ group, subject }))
  } catch (cause) {
    if (cause instanceof ApiError) {
      return []
    }

    throw cause
  }
}

/** Ordena por materia y, dentro de ella, por número de grupo. */
function compareCourses(a: Course, b: Course): number {
  const bySubject = a.subject.nombre.localeCompare(b.subject.nombre, 'es')

  if (bySubject !== 0) return bySubject

  return a.group.num_grupo.localeCompare(b.group.num_grupo, 'es', { numeric: true })
}
