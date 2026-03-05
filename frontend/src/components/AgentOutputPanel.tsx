'use client'
import { useEffect, useState } from 'react'
import { ChevronDown, ChevronRight, FileText, Code2, Paintbrush, CheckSquare } from 'lucide-react'
import { api, type AgentLog } from '@/lib/api'

interface Props {
    taskId: number
    updateTrigger?: number
}

const AGENT_CONFIG: Record<string, { label: string, icon: any, color: string }> = {
    pm: { label: 'PM กุ้ง', icon: FileText, color: 'text-blue-400' },
    ux: { label: 'UX กุ้ง', icon: Paintbrush, color: 'text-pink-400' },
    dev: { label: 'Dev กุ้ง', icon: Code2, color: 'text-green-400' },
    qa: { label: 'QA กุ้ง', icon: CheckSquare, color: 'text-purple-400' },
}

export default function AgentOutputPanel({ taskId, updateTrigger = 0 }: Props) {
    const [logs, setLogs] = useState<AgentLog[]>([])
    const [loading, setLoading] = useState(true)
    const [expanded, setExpanded] = useState<Record<number, boolean>>({})

    useEffect(() => {
        api.tasks.logs(taskId).then(res => {
            setLogs(res.data)

            // Auto-expand the most recent log
            if (res.data.length > 0) {
                const latestId = res.data[res.data.length - 1].id
                setExpanded(prev => ({ ...prev, [latestId]: true }))
            }
        }).finally(() => setLoading(false))
    }, [taskId, updateTrigger])

    if (loading && logs.length === 0) return (
        <div className="text-sm text-cream-muted/50 italic px-1">Waiting for agent logs...</div>
    )
    if (logs.length === 0) return (
        <div className="text-sm text-cream-muted/50 italic px-1">No logs yet.</div>
    )

    const toggle = (id: number) => setExpanded(prev => ({ ...prev, [id]: !prev[id] }))

    return (
        <div className="space-y-3">
            {logs.map(log => {
                const config = AGENT_CONFIG[log.agent] || { label: log.agent, icon: FileText, color: 'text-cream-muted' }
                const Icon = config.icon
                const isExpanded = !!expanded[log.id]

                // Try to pretty-print JSON if possible
                let displayMessage = log.message
                try {
                    const parsed = JSON.parse(log.message)
                    displayMessage = JSON.stringify(parsed, null, 2)
                } catch {
                    // not json
                }

                return (
                    <div key={log.id} className="bg-bark-light rounded-xl border border-clay-dark/20 overflow-hidden">
                        <button
                            onClick={() => toggle(log.id)}
                            className="w-full flex items-center justify-between px-4 py-3 bg-bark border-b border-clay-dark/20 hover:bg-bark-light/50 transition-colors"
                        >
                            <div className="flex items-center gap-2">
                                <Icon size={16} className={config.color} />
                                <span className="text-sm font-semibold text-clay-DEFAULT">{config.label}</span>
                                <span className="text-xs text-cream-muted ml-2">{new Date(log.created_at).toLocaleTimeString()}</span>
                            </div>
                            {isExpanded ? <ChevronDown size={16} className="text-clay-DEFAULT" /> : <ChevronRight size={16} className="text-clay-DEFAULT" />}
                        </button>
                        {isExpanded && (
                            <div className="p-4 overflow-auto max-h-96">
                                <pre className="text-xs font-mono text-cream-muted whitespace-pre-wrap leading-relaxed">
                                    {displayMessage}
                                </pre>
                            </div>
                        )}
                    </div>
                )
            })}
        </div>
    )
}
