'use client'
import { useState, useEffect } from 'react'
import { FileCode, Download, CheckCircle, XCircle } from 'lucide-react'
import { api, type CodeArtifact, type ArtifactVersion } from '@/lib/api'

interface Props {
  taskId: number
  artifacts: CodeArtifact[]
}

export default function CodeViewer({ taskId, artifacts: initialArtifacts }: Props) {
  const [activeIndex, setActiveIndex] = useState(0)
  const [versions, setVersions] = useState<ArtifactVersion[]>([])
  const [selectedVersion, setSelectedVersion] = useState<number | null>(null)
  const [artifacts, setArtifacts] = useState<CodeArtifact[]>(initialArtifacts)
  const [loadingVersion, setLoadingVersion] = useState(false)

  useEffect(() => {
    setArtifacts(initialArtifacts)
    if (initialArtifacts.length > 0) {
      const maxVersion = Math.max(...initialArtifacts.map(a => a.version))
      setSelectedVersion(maxVersion)
    }
  }, [initialArtifacts])

  useEffect(() => {
    if (taskId) {
      api.tasks.artifactVersions(taskId).then(res => {
        setVersions(res.data)
      }).catch(() => {})
    }
  }, [taskId])

  const handleVersionChange = async (version: number) => {
    if (version === selectedVersion) return
    setLoadingVersion(true)
    setActiveIndex(0)
    try {
      const res = await api.tasks.artifacts(taskId, version)
      setArtifacts(res.data)
      setSelectedVersion(version)
    } finally {
      setLoadingVersion(false)
    }
  }

  if (initialArtifacts.length === 0) return null

  const active = artifacts[activeIndex]

  return (
    <div className="bg-bark-light rounded-xl border border-clay-dark/20 overflow-hidden">
      <div className="flex items-center justify-between px-4 py-3 border-b border-clay-dark/20 bg-bark">
        <div className="flex items-center gap-2">
          <FileCode size={16} className="text-clay-DEFAULT" />
          <span className="text-sm font-semibold text-clay-DEFAULT">Code Artifacts</span>
        </div>
        <button
          onClick={() => {
            window.location.href = `${process.env.NEXT_PUBLIC_API_URL}/api/tasks/${taskId}/export`
          }}
          className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-bark bg-clay-DEFAULT hover:bg-clay-light rounded-md transition-colors"
        >
          <Download size={14} />
          Export ZIP
        </button>
      </div>

      {/* Version selector */}
      {versions.length > 1 && (
        <div className="px-4 py-2.5 border-b border-clay-dark/20 bg-bark/60">
          <div className="flex items-center gap-2 flex-wrap">
            <span className="text-xs text-cream-muted/60 font-medium">Version:</span>
            {versions.map(v => (
              <button
                key={v.version}
                onClick={() => handleVersionChange(v.version)}
                disabled={loadingVersion}
                className={`flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold transition-all disabled:opacity-50 ${
                  selectedVersion === v.version
                    ? 'bg-clay-DEFAULT text-bark'
                    : 'bg-bark text-cream-muted border border-clay-dark/30 hover:border-clay-DEFAULT/60'
                }`}
              >
                v{v.version}
                {v.qa_result === 'success' ? (
                  <CheckCircle size={10} className={selectedVersion === v.version ? 'text-bark' : 'text-green-400'} />
                ) : v.qa_result === 'failed' ? (
                  <XCircle size={10} className={selectedVersion === v.version ? 'text-bark' : 'text-red-400'} />
                ) : null}
              </button>
            ))}
            {selectedVersion && (
              <span className="text-[10px] text-cream-muted/50 ml-1">
                Created: {new Date(versions.find(v => v.version === selectedVersion)?.created_at ?? '').toLocaleString()}
              </span>
            )}
          </div>
        </div>
      )}

      {/* Tab bar */}
      <div className="flex gap-1 px-3 pt-2 overflow-x-auto border-b border-clay-dark/20 bg-bark/50">
        {artifacts.map((a, i) => (
          <button
            key={a.id}
            onClick={() => setActiveIndex(i)}
            className={`px-3 py-1.5 text-xs rounded-t-md whitespace-nowrap transition-colors ${i === activeIndex
                ? 'bg-bark-light text-clay-DEFAULT border border-b-bark-light border-clay-dark/30 -mb-px'
                : 'text-cream-muted hover:text-cream-DEFAULT'
              }`}
          >
            {a.filename}
          </button>
        ))}
      </div>

      {/* Code display */}
      <div className="overflow-auto max-h-96 relative">
        {loadingVersion && (
          <div className="absolute inset-0 bg-bark/60 flex items-center justify-center z-10">
            <div className="w-5 h-5 border-2 border-clay-DEFAULT border-t-transparent rounded-full animate-spin" />
          </div>
        )}
        {active && (
          <pre className="p-4 text-xs font-mono text-cream-muted leading-relaxed whitespace-pre-wrap break-all">
            <code>{active.content}</code>
          </pre>
        )}
      </div>
    </div>
  )
}
