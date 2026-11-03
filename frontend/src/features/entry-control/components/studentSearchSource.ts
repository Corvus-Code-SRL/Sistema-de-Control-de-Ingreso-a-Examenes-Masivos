import { entryControlService } from '../services/entryControlService'
import type { StudentSearchSource } from '../types/entry-control.types'

/** Búsqueda del control de ingreso (HU-032), sobre el snapshot del examen abierto. Es la fuente por defecto. */
export const ENTRY_CONTROL_SEARCH: StudentSearchSource = {
  byName: (examId, name, signal) => entryControlService.search(examId, name, signal),
  byIdentifier: (examId, field, prefix, signal) => entryControlService.searchByIdentifier(examId, field, prefix, signal),
}
