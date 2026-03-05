'use client'
import { useState } from 'react'
import { Send, CheckCircle, Loader2 } from 'lucide-react'
import { api, type PmMessage } from '@/lib/api'

interface Props {
  taskId: number
  pmOutput: unknown
  pmMessages: PmMessage[] | null
  onUpdate: () => void
}

function PmOutputDisplay({ output }: { output: unknown }) {
  if (!output || typeof output !== 'object') {
    return <p className="text-cream-muted/60 text-xs italic">No PM output yet.</p>
  }

  const data = output as Record<string, unknown>

  const renderList = (key: string, emoji: string) => {
    const items = data[key]
    if (!Array.isArray(items) || items.length === 0) return null
    return (
      <div className="mb-3">
        <p className="text-xs font-semibold text-clay-DEFAULT mb-1.5">{emoji} {key.charAt(0).toUpperCase() + key.slice(1)}</p>
        <ul className="space-y-1">
          {items.map((item, i) => (
            <li key={i} className="text-xs text-cream-muted flex gap-1.5">
              <span className="text-clay-dark mt-0.5">•</span>
              <span>{typeof item === 'string' ? item : JSON.stringify(item)}</span>
            </li>
          ))}
        </ul>
      </div>
    )
  }

  return (
    <div>
      {renderList('features', '✨')}
      {renderList('requirements', '📋')}
      {renderList('constraints', '⚠️')}
      {!data.features && !data.requirements && !data.constraints && (
        <pre className="text-xs text-cream-muted font-mono whitespace-pre-wrap break-all">
          {JSON.stringify(output, null, 2)}
        </pre>
      )}
    </div>
  )
}

export default function PmChatPanel({ taskId, pmOutput, pmMessages, onUpdate }: Props) {
  const [message, setMessage] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [approving, setApproving] = useState(false)
  const [error, setError] = useState('')

  const handleSendRevision = async () => {
    if (!message.trim()) return
    setSubmitting(true)
    setError('')
    try {
      await api.tasks.pmChat(taskId, message.trim())
      setMessage('')
      onUpdate()
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Failed to send revision'
      setError(msg)
    } finally {
      setSubmitting(false)
    }
  }

  const handleApprove = async () => {
    setApproving(true)
    setError('')
    try {
      await api.tasks.pmApprove(taskId)
      onUpdate()
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Failed to approve'
      setError(msg)
      setApproving(false)
    }
  }

  const userMessages = (pmMessages ?? []).filter(m => m.role === 'user')

  return (
    <div className="bg-bark-light rounded-xl border border-clay-DEFAULT/40 overflow-hidden">
      <div className="flex items-center gap-2 px-5 py-3 border-b border-clay-DEFAULT/30 bg-clay-dark/20">
        <span className="text-base">👁️</span>
        <div>
          <p className="text-sm font-bold text-clay-DEFAULT">PM Review</p>
          <p className="text-xs text-cream-muted/60">Review PM analysis and request revisions or approve to continue</p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-0 divide-y md:divide-y-0 md:divide-x divide-clay-dark/20">
        {/* Left: PM structured output */}
        <div className="p-5">
          <p className="text-xs font-semibold text-cream-muted/60 uppercase tracking-wider mb-3">PM Analysis</p>
          <PmOutputDisplay output={pmOutput} />
        </div>

        {/* Right: Chat interface */}
        <div className="p-5 flex flex-col gap-3">
          <p className="text-xs font-semibold text-cream-muted/60 uppercase tracking-wider">Revision History</p>

          {userMessages.length === 0 ? (
            <p className="text-xs text-cream-muted/40 italic">No revisions requested yet.</p>
          ) : (
            <div className="space-y-2 max-h-40 overflow-y-auto pr-1">
              {userMessages.map((m, i) => (
                <div key={i} className="bg-bark rounded-lg p-2.5 border border-clay-dark/20">
                  <p className="text-[10px] text-clay-DEFAULT/70 mb-1">Revision #{i + 1}</p>
                  <p className="text-xs text-cream-muted">{String(m.content)}</p>
                </div>
              ))}
            </div>
          )}

          {/* Text input */}
          <div className="mt-auto space-y-2">
            <textarea
              value={message}
              onChange={e => setMessage(e.target.value)}
              placeholder="Request a revision... (e.g. Add mobile responsiveness requirement)"
              rows={3}
              disabled={submitting || approving}
              className="w-full bg-bark text-cream-DEFAULT border border-clay-dark/40 rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-clay-DEFAULT placeholder-cream-muted/40 resize-none"
              onKeyDown={e => {
                if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
                  e.preventDefault()
                  handleSendRevision()
                }
              }}
            />

            {error && <p className="text-red-400 text-xs">{error}</p>}

            <div className="flex gap-2">
              <button
                onClick={handleSendRevision}
                disabled={submitting || approving || !message.trim()}
                className="flex items-center gap-1.5 bg-bark hover:bg-bark-light border border-clay-dark/40 hover:border-clay-DEFAULT/60 text-cream-muted hover:text-cream-DEFAULT px-3 py-2 rounded-lg text-xs font-medium transition-colors disabled:opacity-50"
              >
                {submitting ? <Loader2 size={12} className="animate-spin" /> : <Send size={12} />}
                Send Revision
              </button>

              <button
                onClick={handleApprove}
                disabled={submitting || approving}
                className="flex-1 flex items-center justify-center gap-1.5 bg-clay-DEFAULT hover:bg-clay-dark text-bark font-semibold px-3 py-2 rounded-lg text-xs transition-colors disabled:opacity-50"
              >
                {approving ? <Loader2 size={12} className="animate-spin" /> : <CheckCircle size={12} />}
                Approve & Continue →
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
