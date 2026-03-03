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
      })
      const taskId = createRes.data.id
      await api.tasks.start(taskId)
      setTitle('')
      setDescription('')
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
