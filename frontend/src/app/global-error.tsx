'use client'

export default function GlobalError({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <html>
      <body style={{ margin: 0, minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#1a1410', fontFamily: 'system-ui, sans-serif' }}>
        <div style={{ background: '#241e18', border: '1px solid rgba(185,28,28,0.4)', borderRadius: 12, padding: 32, maxWidth: 400, width: '100%', textAlign: 'center' }}>
          <div style={{ fontSize: 40, marginBottom: 12 }}>&#9888;</div>
          <h2 style={{ color: '#f5e6d3', fontSize: 20, fontWeight: 700, margin: '0 0 8px' }}>Something went wrong</h2>
          <p style={{ color: '#a89888', fontSize: 14, margin: '0 0 20px' }}>A critical error occurred. Please try again.</p>
          <button
            onClick={reset}
            style={{ background: '#c87941', color: '#1a1410', fontWeight: 600, border: 'none', padding: '10px 24px', borderRadius: 8, cursor: 'pointer', fontSize: 14 }}
          >
            Try Again
          </button>
        </div>
      </body>
    </html>
  )
}
