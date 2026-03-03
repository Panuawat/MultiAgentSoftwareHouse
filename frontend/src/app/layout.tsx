import type { Metadata } from "next";
import localFont from "next/font/local";
import Link from "next/link";
import "./globals.css";

const geistSans = localFont({
  src: "./fonts/GeistVF.woff",
  variable: "--font-geist-sans",
  weight: "100 900",
});
const geistMono = localFont({
  src: "./fonts/GeistMonoVF.woff",
  variable: "--font-geist-mono",
  weight: "100 900",
});

export const metadata: Metadata = {
  title: "OpenClaw — Multi-Agent Software House",
  description: "AI agent pipeline: PM → UX → Dev → QA",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body className={`${geistSans.variable} ${geistMono.variable} antialiased bg-bark min-h-screen`}>
        <nav className="border-b border-clay-dark/30 bg-bark/90 backdrop-blur-sm sticky top-0 z-50">
          <div className="max-w-6xl mx-auto px-6 h-12 flex items-center gap-6">
            <Link href="/" className="text-clay-DEFAULT font-bold text-lg hover:text-amber transition-colors">
              🦞 OpenClaw
            </Link>
            <span className="text-cream-muted/40 text-xs">Multi-Agent Software House</span>
          </div>
        </nav>
        {children}
      </body>
    </html>
  );
}
