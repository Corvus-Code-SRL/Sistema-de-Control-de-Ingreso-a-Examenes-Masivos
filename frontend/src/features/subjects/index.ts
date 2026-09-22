// API pública del feature subjects
export { SubjectsPage } from './pages/SubjectsPage'
export { AdminSubjectsPage } from './pages/AdminSubjectsPage'
export { useSubjectCatalog } from './hooks/useSubjectCatalog'
export { getSubjectCatalog, DEFAULT_PER_PAGE } from './services/subjectsService'
export { subjectCareerKey } from './types/subject.types'
export type {
  Career,
  SubjectCareer,
  SubjectCatalogMeta,
  SubjectCatalogPage,
  SubjectCatalogResponse,
} from './types/subject.types'
export { EditarMateriaPage } from './pages/EditarMateriaPage'
