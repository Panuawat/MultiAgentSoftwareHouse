import axios from 'axios'

const BASE = process.env.NEXT_PUBLIC_API_URL

export interface Project {
  id: number
  name: string
  description: string | null
  created_at: string
  updated_at: string
  total_cost_usd?: number
}

export interface Task {
  id: number
  project_id: number
  title: string
  description: string
  status: string
  token_budget: number
  token_used: number
  retry_count: number
  pm_review_enabled: boolean
  pm_messages: PmMessage[] | null
  estimated_cost_usd?: number
  agent_output?: Record<string, unknown>
  created_at: string
  updated_at: string
}

export interface PmMessage {
  role: 'user' | 'assistant'
  content: unknown
  created_at: string
}

export interface AgentLog {
  id: number
  task_id: number
  agent: string
  message: string
  created_at: string
}

export interface CodeArtifact {
  id: number
  task_id: number
  filename: string
  content: string
  version: number
  created_at: string
}

export interface ArtifactVersion {
  version: number
  file_count: number
  created_at: string
  qa_result: 'success' | 'failed' | null
}

// All Laravel responses are wrapped in a named key, e.g. { project: {...} }.
// These helpers unwrap them so callers always get { data: T }.
const unwrap =
  <T>(key: string) =>
    (res: { data: Record<string, T> }) => ({ data: res.data[key] as T })

export const api = {
  projects: {
    list: () => axios.get<{ projects: Project[] }>(`${BASE}/api/projects`).then(unwrap<Project[]>('projects')),
    create: (data: { name: string; description?: string }) =>
      axios.post<{ project: Project }>(`${BASE}/api/projects`, data).then(unwrap<Project>('project')),
    get: (id: number) => axios.get<{ project: Project }>(`${BASE}/api/projects/${id}`).then(unwrap<Project>('project')),
    update: (id: number, data: { name: string; description?: string }) =>
      axios.put<{ project: Project }>(`${BASE}/api/projects/${id}`, data).then(unwrap<Project>('project')),
    delete: (id: number) => axios.delete(`${BASE}/api/projects/${id}`),
  },
  tasks: {
    listByProject: (pid: number) => axios.get<{ tasks: Task[] }>(`${BASE}/api/projects/${pid}/tasks`).then(unwrap<Task[]>('tasks')),
    create: (data: { project_id: number; title: string; description: string; token_budget?: number; pm_review_enabled?: boolean }) =>
      axios.post<{ task: Task }>(`${BASE}/api/tasks`, data).then(unwrap<Task>('task')),
    start: (taskId: number) => axios.post<{ task: Task }>(`${BASE}/api/tasks/${taskId}/start`).then(unwrap<Task>('task')),
    resume: (taskId: number, data?: { token_budget: number }) => axios.post<{ task: Task }>(`${BASE}/api/tasks/${taskId}/resume`, data).then(unwrap<Task>('task')),
    cancel: (taskId: number) => axios.post<{ task: Task }>(`${BASE}/api/tasks/${taskId}/cancel`).then(unwrap<Task>('task')),
    get: (taskId: number) => axios.get<{ task: Task }>(`${BASE}/api/tasks/${taskId}`).then(unwrap<Task>('task')),
    logs: (taskId: number) => axios.get<{ logs: AgentLog[] }>(`${BASE}/api/tasks/${taskId}/logs`).then(unwrap<AgentLog[]>('logs')),
    artifacts: (taskId: number, version?: number | 'all') => {
      const params = version !== undefined ? `?version=${version}` : ''
      return axios.get<{ artifacts: CodeArtifact[] }>(`${BASE}/api/tasks/${taskId}/artifacts${params}`).then(unwrap<CodeArtifact[]>('artifacts'))
    },
    artifactVersions: (taskId: number) =>
      axios.get<{ versions: ArtifactVersion[] }>(`${BASE}/api/tasks/${taskId}/artifacts/versions`).then(unwrap<ArtifactVersion[]>('versions')),
    pmChat: (taskId: number, message: string) =>
      axios.post<{ task: Task }>(`${BASE}/api/tasks/${taskId}/pm-chat`, { message }).then(unwrap<Task>('task')),
    pmApprove: (taskId: number) =>
      axios.post<{ task: Task }>(`${BASE}/api/tasks/${taskId}/pm-approve`).then(unwrap<Task>('task')),
  },
  prompts: {
    list: () => axios.get<{ prompts: Record<string, string> }>(`${BASE}/api/prompts`).then(res => ({ data: res.data.prompts })),
    update: (agent: string, content: string) =>
      axios.put(`${BASE}/api/prompts/${agent}`, { content }),
  },
}
