'use client'
import { useState, useEffect } from 'react'
import { X, Save, Loader2 } from 'lucide-react'
import { api } from '@/lib/api'

interface Props {
  open: boolean
  onClose: () => void
}

const AGENT_TABS = [
  { key: 'pm',  label: 'PM',  emoji: '📋', model: 'Gemini 2.0 Flash' },
  { key: 'ux',  label: 'UX',  emoji: '🎨', model: 'Ollama llama3.2' },
  { key: 'dev', label: 'Dev', emoji: '💻', model: 'Gemini 2.0 Flash' },
  { key: 'qa',  label: 'QA',  emoji: '🔍', model: 'Ollama llama3.2' },
]

export default function PromptEditorModal({ open, onClose }: Props) {
  const [activeTab, setActiveTab] = useState('pm')
  const [prompts, setPrompts] = useState<Record<string, string>>({})
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [saveStatus, setSaveStatus] = useState<Record<string, 'idle' | 'saved' | 'error'>>({})

  useEffect(() => {
    if (!open) return
    setLoading(true)
    api.prompts.list().then(res => {
      setPrompts(res.data)
    }).catch(() => {
      setPrompts({})
    }).finally(() => setLoading(false))
  }, [open])

  const handleSave = async (agent: string) => {
    setSaving(true)
    setSaveStatus(prev => ({ ...prev, [agent]: 'idle' }))
    try {
      await api.prompts.update(agent, prompts[agent] ?? '')
      setSaveStatus(prev => ({ ...prev, [agent]: 'saved' }))
      setTimeout(() => setSaveStatus(prev => ({ ...prev, [agent]: 'idle' })), 2500)
    } catch {
      setSaveStatus(prev => ({ ...prev, [agent]: 'error' }))
    } finally {
      setSaving(false)
    }
  }

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-bark/80 backdrop-blur-sm p-4">
      <div className="bg-bark-light border border-clay-dark/30 rounded-2xl w-full max-w-2xl max-h-[85vh] flex flex-col shadow-2xl">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-clay-dark/20">
          <div>
            <h2 className="text-lg font-bold text-clay-DEFAULT">Agent Prompt Editor</h2>
            <p className="text-xs text-cream-muted/60 mt-0.5">Edit system prompts for each agent</p>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-lg hover:bg-bark transition-colors text-cream-muted hover:text-cream-DEFAULT"
          >
            <X size={18} />
          </button>
        </div>

        {/* Tabs */}
        <div className="flex gap-1 px-4 pt-3 border-b border-clay-dark/20">
          {AGENT_TABS.map(tab => (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key)}
              className={`flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-t-lg transition-colors ${
                activeTab === tab.key
                  ? 'bg-bark text-clay-DEFAULT border border-b-bark border-clay-dark/30 -mb-px'
                  : 'text-cream-muted hover:text-cream-DEFAULT'
              }`}
            >
              <span>{tab.emoji}</span>
              {tab.label}
            </button>
          ))}
        </div>

        {/* Content */}
        <div className="flex-1 overflow-hidden flex flex-col p-4 gap-3">
          {loading ? (
            <div className="flex items-center justify-center flex-1 gap-2 text-cream-muted">
              <Loader2 size={20} className="animate-spin text-clay-DEFAULT" />
              <span className="text-sm">Loading prompts...</span>
            </div>
          ) : (
            <>
              {AGENT_TABS.filter(t => t.key === activeTab).map(tab => (
                <div key={tab.key} className="flex-1 flex flex-col gap-3 overflow-hidden">
                  <div className="flex items-center justify-between">
                    <span className="text-xs text-cream-muted/60">
                      Model: <span className="text-clay-DEFAULT font-mono">{tab.model}</span>
                    </span>
                    {saveStatus[tab.key] === 'saved' && (
                      <span className="text-xs text-green-400">✓ Saved</span>
                    )}
                    {saveStatus[tab.key] === 'error' && (
                      <span className="text-xs text-red-400">✗ Save failed</span>
                    )}
                  </div>
                  <textarea
                    value={prompts[tab.key] ?? ''}
                    onChange={e => setPrompts(prev => ({ ...prev, [tab.key]: e.target.value }))}
                    rows={14}
                    className="flex-1 bg-bark text-cream-DEFAULT border border-clay-dark/40 rounded-lg px-4 py-3 text-xs font-mono focus:outline-none focus:border-clay-DEFAULT resize-none leading-relaxed"
                    placeholder={`System prompt for ${tab.label} agent...`}
                    spellCheck={false}
                  />
                  <div className="flex justify-end">
                    <button
                      onClick={() => handleSave(tab.key)}
                      disabled={saving}
                      className="flex items-center gap-2 bg-clay-DEFAULT hover:bg-clay-dark text-bark font-semibold px-4 py-2 rounded-lg text-sm transition-colors disabled:opacity-50"
                    >
                      {saving ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />}
                      Save {tab.label} Prompt
                    </button>
                  </div>
                </div>
              ))}
            </>
          )}
        </div>
      </div>
    </div>
  )
}
