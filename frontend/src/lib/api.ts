import axios from 'axios'

const BASE = process.env.NEXT_PUBLIC_API_URL

export interface Project {
  id: number
  name: string
  description: string | null
  created_at: string
  updated_at: string
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
  created_at: string
  updated_at: string
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

// All Laravel responses are wrapped in a named key, e.g. { project: {...} }.
// These helpers unwrap them so callers always get { data: T }.
const unwrap =
  <T>(key: string) =>
  (res: { data: Record<string, T> }) => ({ data: res.data[key] as T })

export const api = {
  projects: {
    list:   ()                          => axios.get<{ projects: Project[] }>(`${BASE}/api/projects`).then(unwrap<Project[]>('projects')),
    create: (data: { name: string; description?: string }) =>
                                           axios.post<{ project: Project }>(`${BASE}/api/projects`, data).then(unwrap<Project>('project')),
    get:    (id: number)                => axios.get<{ project: Project }>(`${BASE}/api/projects/${id}`).then(unwrap<Project>('project')),
  },
  tasks: {
    listByProject: (pid: number)        => axios.get<{ tasks: Task[] }>(`${BASE}/api/projects/${pid}/tasks`).then(unwrap<Task[]>('tasks')),
    create:        (data: { project_id: number; title: string; description: string; token_budget?: number }) =>
                                           axios.post<{ task: Task }>(`${BASE}/api/tasks`, data).then(unwrap<Task>('task')),
    start:         (taskId: number)     => axios.post<{ task: Task }>(`${BASE}/api/tasks/${taskId}/start`).then(unwrap<Task>('task')),
    get:           (taskId: number)     => axios.get<{ task: Task }>(`${BASE}/api/tasks/${taskId}`).then(unwrap<Task>('task')),
    logs:          (taskId: number)     => axios.get<{ logs: AgentLog[] }>(`${BASE}/api/tasks/${taskId}/logs`).then(unwrap<AgentLog[]>('logs')),
    artifacts:     (taskId: number)     => axios.get<{ artifacts: CodeArtifact[] }>(`${BASE}/api/tasks/${taskId}/artifacts`).then(unwrap<CodeArtifact[]>('artifacts')),
  },
}
