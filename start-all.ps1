# 🦞 OpenClaw Software House — Auto Start Script
# รันไฟล์นี้เพื่อเปิด Services ทั้งหมดในครั้งเดียว
# Usage: .\start-all.ps1

$BACKEND_DIR = "$PSScriptRoot\backend"
$FRONTEND_DIR = "$PSScriptRoot\frontend"
$GOOGLE_API_KEY = "AIzaSyCP_T5nuXmSq-psUX_DEk0dHXVPna2LJ64"

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
