'use client'
import { useEffect, useState } from 'react'
import type { Task } from '@/lib/api'

const TERMINAL = ['completed', 'human_review_required', 'cancelled']

export function useTaskStream(taskId: number | null) {
  const [task, setTask] = useState<Task | null>(null)
  const [streaming, setStreaming] = useState(false)

  useEffect(() => {
    if (!taskId) return

    const es = new EventSource(
      `${process.env.NEXT_PUBLIC_API_URL}/sse/tasks/${taskId}`
    )
    setStreaming(true)

    es.onmessage = (e) => {
      let data: Task & { event?: string }
      try {
        data = JSON.parse(e.data)
      } catch {
        return
      }
      if (data.event === 'close') {
        es.close()
        setStreaming(false)
        return
      }
      setTask(data)
      if (TERMINAL.includes(data.status)) {
        es.close()
        setStreaming(false)
      }
    }

    es.onerror = () => {
      console.warn('SSE connection lost, auto-reconnecting...')
      // EventSource will automatically try to reconnect
    }

    return () => es.close()
  }, [taskId])

  return { task, streaming }
}
