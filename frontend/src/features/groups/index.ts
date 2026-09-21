// API pública del feature groups
export { SubjectGroupsPage } from './pages/SubjectGroupsPage'
export { MyCoursesPage } from './pages/MyCoursesPage'
export { CourseDetailPage } from './pages/CourseDetailPage'
export { useSubjectGroups } from './hooks/useSubjectGroups'
export { useMyCourses } from './hooks/useMyCourses'
export { useGroupDetail } from './hooks/useGroupDetail'
export { getGroupsByPair, getGroup } from './services/groupsService'
export { getMyCourses } from './services/myCoursesService'
export { hasRoster, groupLabel, courseTitle } from './types/group.types'
export type {
  Course,
  Group,
  GroupDetail,
  GroupDetailMeta,
  GroupDetailResponse,
  GroupTeacher,
  Period,
  SubjectGroups,
  SubjectGroupsMeta,
  SubjectGroupsResponse,
} from './types/group.types'
