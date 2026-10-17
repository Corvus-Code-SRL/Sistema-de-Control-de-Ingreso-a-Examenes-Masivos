// API pública del feature subjects
export { SubjectsPage } from './pages/SubjectsPage'
export { AdminSubjectAssignmentsPage } from './pages/AdminSubjectAssignmentsPage'
export { AdminSubjectCatalogPage } from './pages/AdminSubjectCatalogPage'
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
