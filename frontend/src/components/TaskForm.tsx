'use client'
import { useState } from 'react'
import { Send, Loader2 } from 'lucide-react'
import { api } from '@/lib/api'

interface Props {
  projectId: number
  onTaskStarted: (taskId: number) => void
}

export default function TaskForm({ projectId, onTaskStarted }: Props) {
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [pmReviewEnabled, setPmReviewEnabled] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!title.trim() || !description.trim()) return
    setSubmitting(true)
    setError('')
    try {
      const createRes = await api.tasks.create({
        project_id: projectId,
        title: title.trim(),
        description: description.trim(),
        token_budget: 4000,
        pm_review_enabled: pmReviewEnabled,
      })
      const taskId = createRes.data.id
      await api.tasks.start(taskId)
      setTitle('')
      setDescription('')
      setPmReviewEnabled(false)
      onTaskStarted(taskId)
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        'Failed to create or start task'
      setError(msg)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="bg-bark-light rounded-xl p-6 border border-clay-dark/20">
      <h2 className="text-lg font-semibold text-clay-DEFAULT mb-4">New Task Requirement</h2>
      <form onSubmit={handleSubmit} className="space-y-3">
        <input
          type="text"
          placeholder="Task title (e.g. Build a user login page)"
          value={title}
          onChange={e => setTitle(e.target.value)}
          className="w-full bg-bark text-cream-DEFAULT border border-clay-dark/40 rounded-lg px-4 py-2 focus:outline-none focus:border-clay-DEFAULT placeholder-cream-muted/50"
          required
          disabled={submitting}
        />
        <textarea
          placeholder="Describe what you need in natural language. The agents will handle the rest..."
          value={description}
          onChange={e => setDescription(e.target.value)}
          rows={4}
          className="w-full bg-bark text-cream-DEFAULT border border-clay-dark/40 rounded-lg px-4 py-2 focus:outline-none focus:border-clay-DEFAULT placeholder-cream-muted/50 resize-none"
          required
          disabled={submitting}
        />
        <label className="flex items-center gap-2.5 cursor-pointer group select-none w-fit">
          <div className="relative">
            <input
              type="checkbox"
              checked={pmReviewEnabled}
              onChange={e => setPmReviewEnabled(e.target.checked)}
              disabled={submitting}
              className="sr-only"
            />
            <div className={`w-4 h-4 rounded border transition-colors ${pmReviewEnabled ? 'bg-clay-DEFAULT border-clay-DEFAULT' : 'bg-bark border-clay-dark/50 group-hover:border-clay-DEFAULT/60'}`}>
              {pmReviewEnabled && (
                <svg className="w-3 h-3 text-bark mx-auto mt-0.5" fill="none" viewBox="0 0 12 12">
                  <path d="M2 6l3 3 5-5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
              )}
            </div>
          </div>
          <span className="text-sm text-cream-muted group-hover:text-cream-DEFAULT transition-colors">
            👁️ Review PM output before UX starts
          </span>
        </label>
        {error && (
          <p className="text-red-400 text-sm">{error}</p>
        )}
        <button
          type="submit"
          disabled={submitting}
          className="flex items-center gap-2 bg-clay-DEFAULT hover:bg-clay-dark text-bark-DEFAULT font-semibold px-5 py-2 rounded-lg transition-colors disabled:opacity-50"
        >
          {submitting ? <Loader2 size={16} className="animate-spin" /> : <Send size={16} />}
          {submitting ? 'Dispatching...' : 'Dispatch to Agents'}
        </button>
      </form>
    </div>
  )
}
