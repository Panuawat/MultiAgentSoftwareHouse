'use client'
import { useState } from 'react'
import { FileCode } from 'lucide-react'
import type { CodeArtifact } from '@/lib/api'

interface Props {
  artifacts: CodeArtifact[]
}

export default function CodeViewer({ artifacts }: Props) {
  const [activeIndex, setActiveIndex] = useState(0)

  if (artifacts.length === 0) return null

  const active = artifacts[activeIndex]

  return (
    <div className="bg-bark-light rounded-xl border border-clay-dark/20 overflow-hidden">
      <div className="flex items-center gap-2 px-4 py-3 border-b border-clay-dark/20 bg-bark">
        <FileCode size={16} className="text-clay-DEFAULT" />
        <span className="text-sm font-semibold text-clay-DEFAULT">Code Artifacts</span>
      </div>

      {/* Tab bar */}
      <div className="flex gap-1 px-3 pt-2 overflow-x-auto border-b border-clay-dark/20 bg-bark/50">
        {artifacts.map((a, i) => (
          <button
            key={a.id}
            onClick={() => setActiveIndex(i)}
            className={`px-3 py-1.5 text-xs rounded-t-md whitespace-nowrap transition-colors ${
              i === activeIndex
                ? 'bg-bark-light text-clay-DEFAULT border border-b-bark-light border-clay-dark/30 -mb-px'
                : 'text-cream-muted hover:text-cream-DEFAULT'
            }`}
          >
            {a.filename}
            {a.version > 1 && (
              <span className="ml-1 text-clay-dark opacity-70">v{a.version}</span>
            )}
          </button>
        ))}
      </div>

      {/* Code display */}
      <div className="overflow-auto max-h-96">
        <pre className="p-4 text-xs font-mono text-cream-muted leading-relaxed whitespace-pre-wrap break-all">
          <code>{active.content}</code>
        </pre>
      </div>
    </div>
  )
}
