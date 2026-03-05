'use client'
import { useState } from 'react'
import { AlertTriangle, RefreshCw, Play, XCircle } from 'lucide-react'
import { api, type Task } from '@/lib/api'

interface Column {
  label: string
  emoji: string
  statuses: string[]
}

const COLUMNS: Column[] = [
  { label: 'Pending', emoji: '⏳', statuses: ['pending'] },
  { label: 'กุ้ง PM', emoji: '📋', statuses: ['pm_processing', 'pm_review'] },
  { label: 'กุ้ง UX → Dev', emoji: '🎨', statuses: ['ux_processing', 'dev_coding'] },
  { label: 'กุ้ง QA', emoji: '🔍', statuses: ['qa_testing', 'qa_failed'] },
  { label: 'Done', emoji: '✅', statuses: ['completed', 'human_review_required', 'cancelled'] },
]

const STATUS_LABEL: Record<string, string> = {
  pending: 'Pending',
  pm_processing: 'PM Processing',
  pm_review: 'PM Review',
  ux_processing: 'UX Designing',
  dev_coding: 'Dev Coding',
  qa_testing: 'QA Testing',
  qa_failed: 'QA Failed',
  completed: 'Completed',
  human_review_required: 'Human Review',
  cancelled: 'Cancelled',
}

function statusColor(status: string) {
  switch (status) {
    case 'completed': return 'bg-moss/30 text-green-300 border-moss/50'
    case 'human_review_required': return 'bg-red-900/40 text-red-300 border-red-700/50'
    case 'cancelled': return 'bg-gray-700/40 text-gray-400 border-gray-600/50'
    case 'qa_failed': return 'bg-amber/20 text-amber border-amber/40'
    case 'pm_review': return 'bg-clay-dark/30 text-clay-DEFAULT border-clay-DEFAULT/50'
    default: return 'bg-clay-dark/20 text-clay-DEFAULT border-clay-dark/40'
  }
}

interface Props {
  tasks: Task[]
  liveTask: Task | null
}

export default function KanbanBoard({ tasks, liveTask }: Props) {
  const [resumeBudgets, setResumeBudgets] = useState<Record<number, string>>({})
  const [loadingAction, setLoadingAction] = useState<number | null>(null)

  const handleResume = async (task: Task) => {
    try {
      setLoadingAction(task.id)
      const budgetMatch = parseInt(resumeBudgets[task.id] || '0', 10)
      const payload = budgetMatch > task.token_budget ? { token_budget: budgetMatch } : undefined
      await api.tasks.resume(task.id, payload)
      window.location.reload()
    } catch (e) {
      console.error(e)
      setLoadingAction(null)
    }
  }

  const handleCancel = async (taskId: number) => {
    try {
      setLoadingAction(taskId)
      await api.tasks.cancel(taskId)
      window.location.reload()
    } catch (e) {
      console.error(e)
      setLoadingAction(null)
    }
  }

  // Merge liveTask into the list (replace by id if present, else prepend)
  const merged: Task[] = liveTask
    ? tasks.some(t => t.id === liveTask.id)
      ? tasks.map(t => t.id === liveTask.id ? liveTask : t)
      : [liveTask, ...tasks]
    : tasks

  const totalProjectTokens = merged.reduce((sum, t) => sum + t.token_used, 0)

  return (
    <div className="overflow-x-auto">
      <div className="flex gap-4 min-w-max">
        {COLUMNS.map(col => {
          const colTasks = merged.filter(t => col.statuses.includes(t.status))
          const isActive = liveTask ? col.statuses.includes(liveTask.status) : false

          return (
            <div
              key={col.label}
              className={`w-56 flex-shrink-0 rounded-xl border transition-all ${isActive
                ? 'border-clay-DEFAULT/70 bg-bark-light/80 shadow-lg shadow-clay-dark/20'
                : 'border-clay-dark/20 bg-bark-light/30'
                }`}
            >
              <div className={`px-4 py-3 rounded-t-xl border-b border-clay-dark/20 ${isActive ? 'bg-clay-dark/30' : ''
                }`}>
                <span className="mr-2">{col.emoji}</span>
                <span className="font-semibold text-cream-DEFAULT text-sm">{col.label}</span>
                {colTasks.length > 0 && (
                  <span className="ml-2 text-xs bg-clay-dark/40 text-clay-DEFAULT px-1.5 py-0.5 rounded-full">
                    {colTasks.length}
                  </span>
                )}
              </div>

              <div className="p-3 space-y-2 min-h-[120px]">
                {colTasks.map(task => (
                  <div
                    key={task.id}
                    className={`rounded-lg border p-3 text-xs transition-all ${statusColor(task.status)} ${liveTask?.id === task.id ? 'ring-1 ring-clay-DEFAULT/60' : ''
                      }`}
                  >
                    {task.status === 'human_review_required' && (
                      <div className="flex items-center gap-1 mb-2 text-red-300 font-semibold">
                        <AlertTriangle size={12} />
                        Human Review Required
                      </div>
                    )}

                    {task.status === 'pm_review' && (
                      <div className="flex items-center gap-1 mb-2 text-clay-DEFAULT font-semibold">
                        <span className="text-[10px]">👁️</span>
                        Awaiting PM Review
                      </div>
                    )}

                    <p className="font-medium text-sm truncate mb-1">{task.title}</p>
                    <p className="opacity-70 mb-2">{STATUS_LABEL[task.status] ?? task.status}</p>

                    {/* Token usage bar */}
                    {task.token_budget > 0 && (
                      <div>
                        <div className="flex justify-between opacity-60 mb-1">
                          <span>Tokens</span>
                          <span>{task.token_used}/{task.token_budget}</span>
                        </div>
                        <div className="h-1 bg-bark rounded-full overflow-hidden">
                          <div
                            className="h-full bg-clay-DEFAULT rounded-full transition-all"
                            style={{ width: `${Math.min(100, (task.token_used / task.token_budget) * 100)}%` }}
                          />
                        </div>
                      </div>
                    )}

                    {/* Cost badge — show only for completed tasks with non-zero cost */}
                    {task.status === 'completed' && task.estimated_cost_usd !== undefined && task.estimated_cost_usd > 0 && (
                      <div className="mt-2 text-[10px] text-green-300/70 flex items-center gap-1">
                        <span>💰</span>
                        <span>~${task.estimated_cost_usd.toFixed(4)}</span>
                      </div>
                    )}

                    {/* Action buttons for human review */}
                    {task.status === 'human_review_required' && (
                      <div className="mt-3 pt-3 border-t border-red-700/30 space-y-2">
                        <div className="text-[10px] text-cream-muted opacity-80 mb-2 space-y-0.5 bg-bark/30 p-2 rounded border border-bark-light/50">
                          <div className="flex justify-between">
                            <span>Task Used:</span>
                            <span className="text-clay-DEFAULT font-mono">{task.token_used.toLocaleString()}</span>
                          </div>
                          <div className="flex justify-between">
                            <span>Project Total:</span>
                            <span className="text-clay-DEFAULT font-mono">{totalProjectTokens.toLocaleString()}</span>
                          </div>
                        </div>
                        <label className="flex justify-between items-center text-[10px] uppercase text-red-300/70 font-semibold mb-1">
                          <span>New Token Budget</span>
                          <span className="text-clay-DEFAULT/80 text-[9px] normal-case bg-clay-dark/20 px-1 rounded cursor-help" title={`Suggested: Task used tokens (${task.token_used}) + 5000 allowance`}>
                            Suggested: {task.token_used + 5000}
                          </span>
                        </label>
                        <input
                          type="number"
                          placeholder={`${task.token_used + 5000}`}
                          value={resumeBudgets[task.id] || ''}
                          onChange={e => setResumeBudgets(p => ({ ...p, [task.id]: e.target.value }))}
                          className="w-full bg-bark/50 border border-red-900 rounded p-1.5 text-xs text-cream-DEFAULT focus:outline-none focus:border-red-500 mb-2"
                        />
                        <div className="flex gap-2">
                          <button
                            onClick={() => handleResume(task)}
                            disabled={loadingAction === task.id}
                            className="flex-1 flex items-center justify-center gap-1 bg-red-800 hover:bg-red-700 disabled:opacity-50 text-white rounded p-1.5 text-xs transition-colors"
                          >
                            <Play size={12} /> Resume
                          </button>
                          <button
                            onClick={() => handleCancel(task.id)}
                            disabled={loadingAction === task.id}
                            className="flex items-center justify-center gap-1 bg-bark/50 hover:bg-bark disabled:opacity-50 text-cream-muted rounded p-1.5 text-xs transition-colors"
                          >
                            <XCircle size={12} /> Cancel
                          </button>
                        </div>
                      </div>
                    )}

                    {/* Retry badge */}
                    {task.retry_count > 0 && (
                      <div className="flex items-center gap-1 mt-2 text-amber">
                        <RefreshCw size={10} />
                        <span>Retry #{task.retry_count}</span>
                      </div>
                    )}
                  </div>
                ))}

                {colTasks.length === 0 && (
                  <p className="text-cream-muted/30 text-xs text-center pt-4">—</p>
                )}
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
