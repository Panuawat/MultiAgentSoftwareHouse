# 🦞 OpenClaw Software House — Auto Start Script
# รันไฟล์นี้เพื่อเปิด Services ทั้งหมดในครั้งเดียว
# Usage: .\start-all.ps1

$BACKEND_DIR = "$PSScriptRoot\backend"
$FRONTEND_DIR = "$PSScriptRoot\frontend"
# Load API key from .env file or environment variable
$envFile = "$PSScriptRoot\backend\.env"
if (Test-Path $envFile) {
    $match = Select-String -Path $envFile -Pattern '^GOOGLE_API_KEY=(.+)$'
    if ($match) { $GOOGLE_API_KEY = $match.Matches[0].Groups[1].Value }
}
if (-not $GOOGLE_API_KEY) { $GOOGLE_API_KEY = $env:GOOGLE_API_KEY }
if (-not $GOOGLE_API_KEY) {
    Write-Host "⚠ GOOGLE_API_KEY not found in .env or environment!" -ForegroundColor Red
    exit 1
}

Write-Host "`n🦞 Starting OpenClaw Software House...`n" -ForegroundColor Cyan

# 1. PHP Artisan Serve
Write-Host "▶ Starting Laravel API (http://localhost:8000)..." -ForegroundColor Green
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$BACKEND_DIR'; php artisan serve"

Start-Sleep -Seconds 2

# 2. PHP Queue Worker
Write-Host "▶ Starting Queue Worker..." -ForegroundColor Green
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$BACKEND_DIR'; php artisan queue:work --timeout=300 --tries=1"

# 3. Next.js Dev Server
Write-Host "▶ Starting Frontend (http://localhost:3000)..." -ForegroundColor Green
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$FRONTEND_DIR'; npm run dev"

Start-Sleep -Seconds 2

# 4. OpenClaw Gateway
Write-Host "▶ Starting OpenClaw Gateway (@NongCute_bot)..." -ForegroundColor Green
Start-Process powershell -ArgumentList "-NoExit", "-Command", "`$env:GOOGLE_API_KEY='$GOOGLE_API_KEY'; openclaw gateway --port 18789"

Write-Host "`n✅ All services started!" -ForegroundColor Cyan
Write-Host "   API:      http://localhost:8000" -ForegroundColor White
Write-Host "   Frontend: http://localhost:3000" -ForegroundColor White
Write-Host "   OpenClaw: http://127.0.0.1:18789" -ForegroundColor White
Write-Host "   Telegram: @NongCute_bot`n" -ForegroundColor White
