'use client'
import { AlertTriangle } from 'lucide-react'
import Link from 'next/link'

export default function Error({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <div className="min-h-screen bg-bark flex items-center justify-center p-8">
      <div className="bg-bark-light rounded-xl border border-red-700/40 p-8 max-w-md w-full text-center space-y-4">
        <AlertTriangle size={40} className="text-red-400 mx-auto" />
        <h2 className="text-xl font-bold text-cream-DEFAULT">Something went wrong</h2>
        <p className="text-sm text-cream-muted">{error.message}</p>
        <div className="flex gap-3 justify-center pt-2">
          <button
            onClick={reset}
            className="bg-clay-DEFAULT hover:bg-clay-dark text-bark font-semibold px-5 py-2 rounded-lg transition-colors"
          >
            Try Again
          </button>
          <Link
            href="/"
            className="border border-clay-dark/40 text-cream-muted hover:text-cream-DEFAULT px-5 py-2 rounded-lg transition-colors"
          >
            Back to Projects
          </Link>
        </div>
      </div>
    </div>
  )
}
