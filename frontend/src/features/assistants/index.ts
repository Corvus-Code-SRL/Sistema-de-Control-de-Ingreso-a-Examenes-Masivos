// API pública del feature assistants
export { MyAssistantsPage } from './pages/MyAssistantsPage'
export { SearchAssistantInput } from './components/SearchAssistantInput'
export { EnableAssistantExamModal } from './components/EnableAssistantExamModal'
export { AssignAssistantModal } from './components/AssignAssistantModal'
export { MoveAssistantModal } from './components/MoveAssistantModal'
export { RemoveAssistantFromGroupModal } from './components/RemoveAssistantFromGroupModal'
export { AssistantRowMenu } from './components/AssistantRowMenu'

export type {
  Assistant,
  AssistantGroupAssignment,
  AssistantWithGroups,
  AssistantListResponse,
  AssistantWithGroupsListResponse,
  AssistantGroupPayload,
  MoveAssistantPayload,
} from './types/assistant.types'

export {
  getMyAssistants,
  searchAssistants,
  addAssistantToGroup,
  enableAssistantForExam,
  removeAssistantFromGroup,
  removeAssistantFromExam,
  moveAssistantBetweenGroups,
} from './services/assistantService'