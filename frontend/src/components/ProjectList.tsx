'use client'
import { useEffect, useState } from 'react'
import Link from 'next/link'
import { PlusCircle, FolderOpen, Loader2 } from 'lucide-react'
import { api, type Project } from '@/lib/api'

export default function ProjectList() {
  const [projects, setProjects] = useState<Project[]>([])
  const [loading, setLoading] = useState(true)
  const [creating, setCreating] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    api.projects.list()
      .then(r => setProjects(r.data))
      .catch(() => setError('Failed to load projects'))
      .finally(() => setLoading(false))
  }, [])

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!name.trim()) return
    setCreating(true)
    setError('')
    try {
      const r = await api.projects.create({ name: name.trim(), description: description.trim() || undefined })
      setProjects(prev => [r.data, ...prev])
      setName('')
      setDescription('')
      setShowForm(false)
    } catch {
      setError('Failed to create project')
    } finally {
      setCreating(false)
    }
  }

  return (
    <div className="min-h-screen bg-bark text-cream-DEFAULT p-8">
      <div className="max-w-4xl mx-auto">
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-3xl font-bold text-clay-DEFAULT">OpenClaw</h1>
            <p className="text-cream-muted text-sm mt-1">Multi-Agent Software House</p>
          </div>
          <button
            onClick={() => setShowForm(!showForm)}
            className="flex items-center gap-2 bg-clay-DEFAULT hover:bg-clay-dark text-bark-DEFAULT font-semibold px-4 py-2 rounded-lg transition-colors"
          >
            <PlusCircle size={18} />
            New Project
          </button>
        </div>

        {showForm && (
          <form onSubmit={handleCreate} className="bg-bark-light rounded-xl p-6 mb-6 border border-clay-dark/30">
            <h2 className="text-lg font-semibold text-clay-DEFAULT mb-4">Create Project</h2>
            <div className="space-y-3">
              <input
                type="text"
                placeholder="Project name"
                value={name}
                onChange={e => setName(e.target.value)}
                className="w-full bg-bark text-cream-DEFAULT border border-clay-dark/40 rounded-lg px-4 py-2 focus:outline-none focus:border-clay-DEFAULT placeholder-cream-muted/50"
                required
              />
              <textarea
                placeholder="Description (optional)"
                value={description}
                onChange={e => setDescription(e.target.value)}
                rows={2}
                className="w-full bg-bark text-cream-DEFAULT border border-clay-dark/40 rounded-lg px-4 py-2 focus:outline-none focus:border-clay-DEFAULT placeholder-cream-muted/50 resize-none"
              />
            </div>
            <div className="flex gap-3 mt-4">
              <button
                type="submit"
                disabled={creating}
                className="flex items-center gap-2 bg-clay-DEFAULT hover:bg-clay-dark text-bark-DEFAULT font-semibold px-4 py-2 rounded-lg transition-colors disabled:opacity-50"
              >
                {creating && <Loader2 size={16} className="animate-spin" />}
                Create
              </button>
              <button
                type="button"
                onClick={() => setShowForm(false)}
                className="px-4 py-2 rounded-lg border border-clay-dark/40 text-cream-muted hover:text-cream-DEFAULT transition-colors"
              >
                Cancel
              </button>
            </div>
          </form>
        )}

        {error && (
          <div className="bg-red-900/30 border border-red-700 text-red-300 rounded-lg p-3 mb-4 text-sm">
            {error}
          </div>
        )}

        {loading ? (
          <div className="flex justify-center py-20">
            <Loader2 size={32} className="animate-spin text-clay-DEFAULT" />
          </div>
        ) : projects.length === 0 ? (
          <div className="text-center py-20 text-cream-muted">
            <FolderOpen size={48} className="mx-auto mb-4 opacity-40" />
            <p>No projects yet. Create one to get started.</p>
          </div>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2">
            {projects.map(p => (
              <Link
                key={p.id}
                href={`/projects/${p.id}`}
                className="group block bg-bark-light hover:bg-bark-light/80 rounded-xl p-5 border border-clay-dark/20 hover:border-clay-DEFAULT/50 transition-all"
              >
                <div className="flex items-start gap-3">
                  <FolderOpen size={20} className="text-clay-DEFAULT mt-0.5 flex-shrink-0" />
                  <div>
                    <h3 className="font-semibold text-cream-DEFAULT group-hover:text-clay-DEFAULT transition-colors">
                      {p.name}
                    </h3>
                    {p.description && (
                      <p className="text-cream-muted text-sm mt-1 line-clamp-2">{p.description}</p>
                    )}
                    <p className="text-xs text-cream-muted/60 mt-2">
                      {new Date(p.created_at).toLocaleDateString()}
                    </p>
                  </div>
                </div>
              </Link>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
