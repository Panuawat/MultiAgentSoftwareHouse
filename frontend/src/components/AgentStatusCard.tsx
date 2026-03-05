'use client'
import type { Task } from '@/lib/api'

interface Agent {
  name: string
  emoji: string
  activeStatuses: string[]
  doneStatuses: string[]
  errorStatuses: string[]
}

const AGENTS: Agent[] = [
  {
    name: 'กุ้ง PM',
    emoji: '📋',
    activeStatuses: ['pm_processing', 'pm_review'],
    doneStatuses: ['ux_processing', 'dev_coding', 'qa_testing', 'qa_failed', 'completed', 'human_review_required', 'cancelled'],
    errorStatuses: [],
  },
  {
    name: 'กุ้ง UX',
    emoji: '🎨',
    activeStatuses: ['ux_processing'],
    doneStatuses: ['dev_coding', 'qa_testing', 'qa_failed', 'completed', 'human_review_required', 'cancelled'],
    errorStatuses: [],
  },
  {
    name: 'กุ้ง Dev',
    emoji: '💻',
    activeStatuses: ['dev_coding'],
    doneStatuses: ['qa_testing', 'qa_failed', 'completed', 'human_review_required'],
    errorStatuses: ['cancelled'],
  },
  {
    name: 'กุ้ง QA',
    emoji: '🔍',
    activeStatuses: ['qa_testing'],
    doneStatuses: ['completed'],
    errorStatuses: ['qa_failed', 'human_review_required', 'cancelled'],
  },
]

function getAgentState(agent: Agent, task: Task | null): 'idle' | 'working' | 'done' | 'error' {
  if (!task) return 'idle'
  if (agent.activeStatuses.includes(task.status)) return 'working'
  if (agent.doneStatuses.includes(task.status)) return 'done'
  if (agent.errorStatuses.includes(task.status)) return 'error'
  return 'idle'
}

const STATE_STYLES: Record<string, string> = {
  idle:    'border-bark-light bg-bark-light/30 text-cream-muted',
  working: 'border-clay-DEFAULT bg-clay-dark/20 text-clay-DEFAULT ring-1 ring-clay-DEFAULT/40',
  done:    'border-moss/50 bg-moss/20 text-green-300',
  error:   'border-red-700/50 bg-red-900/20 text-red-300',
}

const STATE_LABEL: Record<string, string> = {
  idle: 'Idle', working: 'Working', done: 'Done', error: 'Error',
}

interface Props {
  task: Task | null
}

export default function AgentStatusCard({ task }: Props) {
  return (
    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
      {AGENTS.map(agent => {
        const state = getAgentState(agent, task)
        return (
          <div
            key={agent.name}
            className={`rounded-xl border p-4 transition-all duration-500 ${STATE_STYLES[state]}`}
          >
            <div className="text-2xl mb-2">{agent.emoji}</div>
            <div className="font-semibold text-sm">{agent.name}</div>
            <div className="text-xs mt-1 opacity-80">{STATE_LABEL[state]}</div>
            {state === 'working' && task && (
              <div className="mt-2">
                <div className="flex justify-between text-xs opacity-60 mb-1">
                  <span>Tokens</span>
                  <span>{task.token_used}/{task.token_budget}</span>
                </div>
                <div className="h-1 bg-bark rounded-full overflow-hidden">
                  <div
                    className="h-full bg-clay-DEFAULT rounded-full transition-all duration-700"
                    style={{ width: `${Math.min(100, (task.token_used / task.token_budget) * 100)}%` }}
                  />
                </div>
              </div>
            )}
            {task && (
              <div className="text-xs opacity-40 mt-2 truncate">
                {new Date(task.updated_at).toLocaleTimeString()}
              </div>
            )}
          </div>
        )
      })}
    </div>
  )
}
