'use client'
import { AlertTriangle, RefreshCw } from 'lucide-react'
import type { Task } from '@/lib/api'

interface Column {
  label: string
  emoji: string
  statuses: string[]
}

const COLUMNS: Column[] = [
  { label: 'Pending',      emoji: '⏳', statuses: ['pending'] },
  { label: 'กุ้ง PM',      emoji: '📋', statuses: ['pm_processing'] },
  { label: 'กุ้ง UX → Dev', emoji: '🎨', statuses: ['ux_processing', 'dev_coding'] },
  { label: 'กุ้ง QA',      emoji: '🔍', statuses: ['qa_testing', 'qa_failed'] },
  { label: 'Done',         emoji: '✅', statuses: ['completed', 'human_review_required', 'cancelled'] },
]

const STATUS_LABEL: Record<string, string> = {
  pending:                'Pending',
  pm_processing:          'PM Processing',
  ux_processing:          'UX Designing',
  dev_coding:             'Dev Coding',
  qa_testing:             'QA Testing',
  qa_failed:              'QA Failed',
  completed:              'Completed',
  human_review_required:  'Human Review',
  cancelled:              'Cancelled',
}

function statusColor(status: string) {
  switch (status) {
    case 'completed':             return 'bg-moss/30 text-green-300 border-moss/50'
    case 'human_review_required': return 'bg-red-900/40 text-red-300 border-red-700/50'
    case 'cancelled':             return 'bg-gray-700/40 text-gray-400 border-gray-600/50'
    case 'qa_failed':             return 'bg-amber/20 text-amber border-amber/40'
    default:                      return 'bg-clay-dark/20 text-clay-DEFAULT border-clay-dark/40'
  }
}

interface Props {
  tasks: Task[]
  liveTask: Task | null
}

export default function KanbanBoard({ tasks, liveTask }: Props) {
  // Merge liveTask into the list (replace by id if present, else prepend)
  const merged: Task[] = liveTask
    ? tasks.some(t => t.id === liveTask.id)
      ? tasks.map(t => t.id === liveTask.id ? liveTask : t)
      : [liveTask, ...tasks]
    : tasks

  return (
    <div className="overflow-x-auto">
      <div className="flex gap-4 min-w-max">
        {COLUMNS.map(col => {
          const colTasks = merged.filter(t => col.statuses.includes(t.status))
          const isActive = liveTask ? col.statuses.includes(liveTask.status) : false

          return (
            <div
              key={col.label}
              className={`w-56 flex-shrink-0 rounded-xl border transition-all ${
                isActive
                  ? 'border-clay-DEFAULT/70 bg-bark-light/80 shadow-lg shadow-clay-dark/20'
                  : 'border-clay-dark/20 bg-bark-light/30'
              }`}
            >
              <div className={`px-4 py-3 rounded-t-xl border-b border-clay-dark/20 ${
                isActive ? 'bg-clay-dark/30' : ''
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
                    className={`rounded-lg border p-3 text-xs transition-all ${statusColor(task.status)} ${
                      liveTask?.id === task.id ? 'ring-1 ring-clay-DEFAULT/60' : ''
                    }`}
                  >
                    {task.status === 'human_review_required' && (
                      <div className="flex items-center gap-1 mb-2 text-red-300 font-semibold">
                        <AlertTriangle size={12} />
                        Human Review Required
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
