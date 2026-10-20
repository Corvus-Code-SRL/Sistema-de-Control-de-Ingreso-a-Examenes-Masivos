// API pública del feature groups
export { SubjectGroupsPage } from './pages/SubjectGroupsPage'
export { MyCoursesPage } from './pages/MyCoursesPage'
export { GroupDetailPage } from './pages/GroupDetailPage'
export { GroupEditPage } from './pages/GroupEditPage'
export { useSubjectGroups } from './hooks/useSubjectGroups'
export { useMyCourses } from './hooks/useMyCourses'
export { useGroupDetail } from './hooks/useGroupDetail'
export { getGroupsByPair, getGroup, getGroupStudents } from './services/groupsService'
export { getMyCourses } from './services/myCoursesService'
export { hasRoster, groupLabel, courseTitle } from './types/group.types'
export { CourseAccessDenied } from './components/CourseAccessDenied'
export { GroupNotFound } from './components/GroupNotFound'
export {
  MY_COURSES_PATH,
  groupDetailPath,
  groupEditPath,
  groupIdFrom,
  groupRosterPath,
} from './utils/paths'
export type {
  Course,
  Group,
  GroupDetail,
  GroupDetailMeta,
  GroupDetailResponse,
  GroupStudent,
  GroupStudentsPage,
  GroupTeacher,
  Period,
  SubjectGroups,
  SubjectGroupsMeta,
  SubjectGroupsResponse,
} from './types/group.types'
