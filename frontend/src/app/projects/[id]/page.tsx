'use client'
import { useEffect, useState, useCallback } from 'react'
import { useParams } from 'next/navigation'
import Link from 'next/link'
import { ChevronLeft, Loader2, AlertTriangle, CheckCircle, Radio, Settings } from 'lucide-react'
import { api, type Project, type Task, type CodeArtifact } from '@/lib/api'
import { useTaskStream } from '@/hooks/useTaskStream'
import TaskForm from '@/components/TaskForm'
import KanbanBoard from '@/components/KanbanBoard'
import AgentStatusCard from '@/components/AgentStatusCard'
import CodeViewer from '@/components/CodeViewer'
import AgentOutputPanel from '@/components/AgentOutputPanel'
import PromptEditorModal from '@/components/PromptEditorModal'
import PmChatPanel from '@/components/PmChatPanel'

export default function ProjectPage() {
  const { id } = useParams<{ id: string }>()
  const projectId = parseInt(id, 10)

  const [project, setProject] = useState<Project | null>(null)
  const [tasks, setTasks] = useState<Task[]>([])
  const [artifacts, setArtifacts] = useState<CodeArtifact[]>([])
  const [activeTaskId, setActiveTaskId] = useState<number | null>(null)
  const [loadingProject, setLoadingProject] = useState(true)
  const [updateTrigger, setUpdateTrigger] = useState(0)
  const [promptModalOpen, setPromptModalOpen] = useState(false)

  const { task: liveTask, streaming } = useTaskStream(activeTaskId)

  // Load project and tasks on mount — auto-restore the most recently active task
  useEffect(() => {
    Promise.all([
      api.projects.get(projectId),
      api.tasks.listByProject(projectId),
    ]).then(([pRes, tRes]) => {
      setProject(pRes.data)
      const loadedTasks = tRes.data
      setTasks(loadedTasks)

      if (loadedTasks.length > 0) {
        const sorted = [...loadedTasks].sort(
          (a, b) => new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime()
        )
        const restored = sorted.find(t => t.status !== 'cancelled') ?? sorted[0]
        setActiveTaskId(restored.id)

        if (restored.status === 'completed') {
          api.tasks.artifacts(restored.id).then(aRes => setArtifacts(aRes.data)).catch(() => {})
        }
      }
    }).finally(() => setLoadingProject(false))
  }, [projectId])

  // When liveTask reaches terminal status, refresh task list and fetch artifacts
  const handleTaskComplete = useCallback(async (taskId: number, status: string) => {
    const tRes = await api.tasks.listByProject(projectId)
    setTasks(tRes.data)
    setUpdateTrigger(prev => prev + 1)
    if (status === 'completed') {
      try {
        const aRes = await api.tasks.artifacts(taskId)
        setArtifacts(aRes.data)
      } catch { /* no artifacts */ }
    }
  }, [projectId])

  useEffect(() => {
    if (!liveTask) return
    const TERMINAL = ['completed', 'human_review_required', 'cancelled']
    if (TERMINAL.includes(liveTask.status)) {
      handleTaskComplete(liveTask.id, liveTask.status)
    } else {
      // Refresh logs panel on every mid-pipeline SSE event (e.g. QA started)
      setUpdateTrigger(prev => prev + 1)
    }
  }, [liveTask, handleTaskComplete])

  const handleTaskStarted = (taskId: number) => {
    setActiveTaskId(taskId)
    setArtifacts([])
    api.tasks.get(taskId).then(r => {
      setTasks(prev =>
        prev.some(t => t.id === taskId)
          ? prev.map(t => t.id === taskId ? r.data : t)
          : [r.data, ...prev]
      )
    })
  }

  // Refresh active task data (used by PmChatPanel after actions)
  const handlePmUpdate = useCallback(() => {
    if (activeTaskId) {
      api.tasks.get(activeTaskId).then(r => {
        setTasks(prev =>
          prev.map(t => t.id === activeTaskId ? r.data : t)
        )
        setUpdateTrigger(prev => prev + 1)
      })
    }
  }, [activeTaskId])

  if (loadingProject) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <Loader2 size={32} className="animate-spin text-clay-DEFAULT" />
      </div>
    )
  }

  if (!project) {
    return (
      <div className="text-center py-20 text-cream-muted">
        <p>Project not found.</p>
        <Link href="/" className="text-clay-DEFAULT hover:underline mt-2 inline-block">← Back to projects</Link>
      </div>
    )
  }

  const currentTask = liveTask ?? (activeTaskId ? tasks.find(t => t.id === activeTaskId) ?? null : null)
  const isTerminal = currentTask
    ? ['completed', 'human_review_required', 'cancelled'].includes(currentTask.status)
    : false

  const totalCost = project.total_cost_usd ?? 0

  return (
    <div className="min-h-screen bg-bark text-cream-DEFAULT">
      <div className="max-w-6xl mx-auto px-6 py-8 space-y-8">
        {/* Header */}
        <div>
          <Link href="/" className="flex items-center gap-1 text-cream-muted hover:text-clay-DEFAULT text-sm mb-3 w-fit transition-colors">
            <ChevronLeft size={16} />
            Projects
          </Link>
          <div className="flex items-start justify-between gap-4">
            <div>
              <div className="flex items-center gap-3 flex-wrap">
                <h1 className="text-2xl font-bold text-clay-DEFAULT">{project.name}</h1>
                {totalCost > 0 && (
                  <span className="text-xs bg-bark-light border border-clay-dark/30 text-clay-DEFAULT/80 px-2.5 py-1 rounded-full font-mono">
                    💰 Total API Cost: ~${totalCost.toFixed(4)}
                  </span>
                )}
              </div>
              {project.description && (
                <p className="text-cream-muted text-sm mt-1">{project.description}</p>
              )}
            </div>
            <button
              onClick={() => setPromptModalOpen(true)}
              className="flex-shrink-0 flex items-center gap-2 bg-bark-light hover:bg-bark border border-clay-dark/30 hover:border-clay-DEFAULT/50 text-cream-muted hover:text-cream-DEFAULT px-4 py-2 rounded-lg text-sm font-medium transition-colors"
            >
              <Settings size={14} />
              Edit Prompts
            </button>
          </div>
        </div>

        {/* Live status banner */}
        {streaming && currentTask && currentTask.status !== 'pm_review' && (
          <div className="flex items-center gap-3 bg-clay-dark/30 border border-clay-DEFAULT/40 rounded-xl px-5 py-3">
            <Radio size={16} className="text-clay-DEFAULT animate-pulse" />
            <span className="text-clay-DEFAULT font-semibold text-sm">
              Live — Agent pipeline running: <span className="font-mono">{currentTask.status}</span>
            </span>
          </div>
        )}

        {/* Terminal status banners */}
        {isTerminal && currentTask && (
          <>
            {currentTask.status === 'completed' && (
              <div className="flex items-center gap-3 bg-moss/20 border border-moss/50 rounded-xl px-5 py-3">
                <CheckCircle size={16} className="text-green-400" />
                <span className="text-green-300 font-semibold text-sm">Task completed successfully!</span>
              </div>
            )}
            {currentTask.status === 'human_review_required' && (
              <div className="flex items-center gap-3 bg-red-900/30 border border-red-700/50 rounded-xl px-5 py-3">
                <AlertTriangle size={16} className="text-red-400" />
                <span className="text-red-300 font-semibold text-sm">
                  Human review required — max retries exceeded. Check agent logs.
                </span>
              </div>
            )}
          </>
        )}

        {/* Task Form */}
        <TaskForm projectId={projectId} onTaskStarted={handleTaskStarted} />

        {/* PM Chat Panel — shown when task is in pm_review */}
        {currentTask?.status === 'pm_review' && (
          <div>
            <h2 className="text-sm font-semibold text-cream-muted uppercase tracking-wider mb-3">PM Review</h2>
            <PmChatPanel
              taskId={currentTask.id}
              pmOutput={(currentTask.agent_output as Record<string, unknown>)?.pm}
              pmMessages={currentTask.pm_messages}
              onUpdate={handlePmUpdate}
            />
          </div>
        )}

        {/* Agent Status Cards */}
        {(currentTask || tasks.length > 0) && (
          <div>
            <h2 className="text-sm font-semibold text-cream-muted uppercase tracking-wider mb-3">Agent Status</h2>
            <AgentStatusCard task={currentTask} />
          </div>
        )}

        {/* Kanban Board */}
        {tasks.length > 0 && (
          <div>
            <h2 className="text-sm font-semibold text-cream-muted uppercase tracking-wider mb-3">Pipeline</h2>
            <KanbanBoard
              tasks={tasks}
              liveTask={liveTask}
              onTaskUpdated={(updated) => setTasks(prev => prev.map(t => t.id === updated.id ? updated : t))}
              onResumed={(taskId) => setActiveTaskId(taskId)}
            />
          </div>
        )}

        {/* Agent Logs */}
        {currentTask && (
          <div>
            <h2 className="text-sm font-semibold text-cream-muted uppercase tracking-wider mb-3">Agent Logs & Output</h2>
            <AgentOutputPanel taskId={currentTask.id} updateTrigger={updateTrigger} />
          </div>
        )}

        {/* Code Viewer */}
        {artifacts.length > 0 && currentTask && (
          <div>
            <h2 className="text-sm font-semibold text-cream-muted uppercase tracking-wider mb-3">Generated Code</h2>
            <CodeViewer taskId={currentTask.id} artifacts={artifacts} />
          </div>
        )}
      </div>

      {/* Prompt Editor Modal */}
      <PromptEditorModal open={promptModalOpen} onClose={() => setPromptModalOpen(false)} />
    </div>
  )
}
