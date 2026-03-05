# OpenClaw Software House — CLAUDE.md

> ไฟล์นี้คือ "คู่มือบริษัท" ที่ Claude Code (หรือ AI Assistant อื่นๆ) จะอ่านทุก Session
> ห้ามลบหรือย้าย ให้ Update เพิ่มเติมเมื่อ Architecture เปลี่ยน

---

## 🦞 Project Overview

**OpenClaw Software House** คือ Multi-Agent System ที่จำลองบริษัท Software House
โดยมี AI Agent 4 ตัว ("น้องกุ้ง") ทำงานร่วมกันแบบ Pipeline อัตโนมัติ

- **Goal:** รับ Requirement ภาษาคน → ผลิต Web App ออกมาโดยอัตโนมัติ
- **Portfolio Target:** แสดงทักษะ Multi-Agent Orchestration, Laravel API, Next.js Dashboard

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| Orchestrator | OpenClaw CLI (Shell/Python Scripts) |
| AI Brain (Complex) | Gemini API |
| AI Brain (Routine) | Ollama (Local) |
| Backend & State | Laravel 11 (REST API + MySQL) |
| Queue | Laravel Queue (Database Driver → Redis ใน Production) |
| Frontend | Next.js 14 + Tailwind CSS |
| Realtime | Server-Sent Events (SSE) (Event-Driven + Auto-Reconnect) |
| Code Highlighting | Shiki (github-dark theme) |
| Extras | ZIP Export, Live Code Preview (iframe + Tailwind CDN), Auto GitHub Push |

---

## 🦞 Agent Roster (น้องกุ้งทั้ง 4 ตัว)

### 1. กุ้ง PM (Product Manager)
- **Model:** Gemini API
- **Input:** Natural Language Requirement จากผู้ใช้
- **Output:** JSON `{ features: [], requirements: [], constraints: [] }`
- **ห้าม:** ตัดสินใจเรื่อง UI หรือโค้ดเอง

### 2. กุ้ง UX/UI (Designer)
- **Model:** Ollama (Local)
- **Input:** JSON Requirements จาก PM
- **Output:** JSON `{ layout: {}, components: [], tailwind_classes: {} }`
- **ห้าม:** ส่ง Output ที่ไม่ผ่าน HTML Structure Validation

### 3. กุ้ง Dev (Full-Stack Developer)
- **Model:** Gemini API
- **Input:** Requirements + UI Structure
- **Output:** React/Next.js files ที่รันได้จริง
- **ห้าม:** เกิน Token Budget ที่กำหนดต่อ Task

### 4. กุ้ง QA (Tester)
- **Model:** Ollama + OpenClaw CLI
- **Input:** Code files จาก Dev
- **Output:** JSON `{ passed: bool, errors: [], report: "" }`
- **ห้าม:** ผ่านงานที่มี Lint Error หรือ TypeScript Error

---

## 🔄 State Machine (กฎเหล็กของระบบ)

```
PM (pending) → UX/UI (designing) → Dev (coding) → QA (testing)
                                        ↑               |
                                        └── QA_FAILED ──┘
                                        (สูงสุด 3 ครั้ง)
                                              |
                                        (ครั้งที่ 4)
                                              ↓
                                    HUMAN_REVIEW_REQUIRED
```

### Task Status ที่ถูกต้องทั้งหมด
```
pending → pm_processing → ux_processing → dev_coding → qa_testing → qa_failed → completed → human_review_required → cancelled
```

---

## ⚠️ กฎที่ห้ามละเมิดเด็ดขาด (Non-Negotiable Rules)

### 1. ทุก State Transition ต้องใช้ DB Transaction + Pessimistic Lock
```php
// ✅ ถูกต้อง — ต้องทำแบบนี้เสมอ
DB::transaction(function () use ($taskId, $newStatus) {
    $task = Task::where('id', $taskId)->lockForUpdate()->first();
    $task->status = $newStatus;
    $task->save();
});

// ❌ ห้ามทำ — Race Condition ชัวร์
$task = Task::find($taskId);
$task->status = $newStatus;
$task->save();
```

### 2. ทุก Agent Job ต้องเช็ค Token Budget ก่อนรัน
```php
if ($task->token_used >= $task->token_budget) {
    $task->escalate('TOKEN_BUDGET_EXCEEDED');
    return;
}
```

### 3. Fallback Loop ต้องไม่เกิน 3 ครั้ง
```php
if ($task->retry_count >= 3) {
    $task->status = 'human_review_required';
    // Notify Dashboard via SSE
}
```

### 4. ห้าม Call Gemini API จริงในโหมด Testing
```bash
# ใช้ Mock Mode เสมอตอน Dev
APP_AGENT_MODE=mock php artisan serve
```

---

## 🗄️ Database Schema Overview

ดูรายละเอียดได้ที่ `database/migrations/`

**ตารางหลัก:**
- `projects` — โปรเจกต์หลัก
- `tasks` — งานย่อย (มี `token_budget`, `token_used`, `retry_count`, `base_task_id`)
- `agent_logs` — ประวัติการทำงานของกุ้งแต่ละตัว
- `code_artifacts` — โค้ดที่กุ้ง Dev เขียน (Versioning)

---

## 📁 Project Structure

```
openclaw/
├── CLAUDE.md                  ← ไฟล์นี้
├── orchestrator/              ← OpenClaw Scripts (Python/Shell)
│   ├── main.py
│   ├── agents/
│   │   ├── pm_agent.py
│   │   ├── ux_agent.py
│   │   ├── dev_agent.py
│   │   └── qa_agent.py
│   ├── prompts/               ← ไฟล์ตั้งต้น (System Prompts) ของน้องกุ้ง
│   │   ├── pm_system.txt
│   │   ├── ux_system.txt
│   │   ├── dev_system.txt
│   │   └── qa_system.txt
│   └── mock/                  ← Mock responses สำหรับ Testing
├── backend/                   ← Laravel 11
│   ├── app/
│   │   ├── Events/            ← สำหรับส่ง Event (SSE) ไปหน้า Dashboard
│   │   │   └── TaskStatusUpdated.php
│   │   ├── Listeners/
│   │   │   └── BroadcastTaskUpdate.php  ← เขียน signal file สำหรับ SSE
│   │   ├── Services/
│   │   │   ├── StateMachineService.php
│   │   │   └── TelegramService.php
│   │   ├── Jobs/              ← Queue Jobs ของกุ้งแต่ละตัว + PushToGithubJob
│   │   ├── Http/Controllers/
│   │   │   └── TelegramWebhookController.php  ← Telegram inline button handler
│   │   └── Console/Commands/
│   │       └── TelegramSetWebhookCommand.php  ← php artisan telegram:set-webhook
│   └── database/migrations/
└── frontend/                  ← Next.js 14
    └── src/
        ├── components/
        │   ├── KanbanBoard.tsx
        │   ├── AgentStatusCard.tsx
        │   ├── CodeViewer.tsx         ← Shiki syntax highlighting + Live Preview (iframe)
        │   ├── AgentOutputPanel.tsx
        │   ├── ProjectList.tsx        ← Inline edit/delete projects
        │   └── PromptEditorModal.tsx
        ├── hooks/
        │   └── useTaskStream.ts       ← SSE hook (EventSource)
        └── app/
            ├── error.tsx              ← Route-level error boundary
            └── global-error.tsx       ← Layout-level error boundary
```

---

## 🧪 Development Workflow

```bash
# 1. เริ่ม Session ใหม่ทุกครั้ง
/clear

# 2. รัน Mock Mode (ไม่เสีย Token)
APP_AGENT_MODE=mock

# 3. ก่อนเพิ่ม Feature ใหม่ ให้ Plan ก่อนเสมอ
# พิมพ์: "Plan การเพิ่ม [feature] โดยไม่แตะ State Machine เดิม"

# 4. Test ทุกครั้งหลัง State Transition เปลี่ยน
php artisan test --filter=StateMachineTest
```

---

## 🎯 Current Phase

**Phase 1: System Architecture & Database Design**

- [x] สร้าง Laravel Project
- [x] เขียน Database Migrations ทั้งหมด
- [x] เขียน StateMachineService.php
- [x] เขียน Unit Tests สำหรับ State Transitions
- [x] Setup Mock Agent Mode

**Phase 2: Core Orchestration & AI Integration**

- [x] พัฒนา Agent Jobs (PM, UX, Dev, QA)
- [x] เชื่อมต่อ Gemini API และ Ollama
- [x] สร้าง SSE Controller สำหรับ Realtime Updates
- [x] พัฒนา Frontend Kanban Board และ Task Execution Flow

**Phase 3: Stabilization & UI Improvements**

- [x] เพิ่มระบบ Auto-reconnect ให้ SSE
- [x] เพิ่มปุ่ม Export Code เป็นไฟล์ .zip
- [x] สร้าง AgentOutputPanel แสดง Log ความคิดของ Agent
- [x] เพิ่มระบบจัดการ Token Budget และ Resume Task กรณีงบหมด
- [x] ฟีเจอร์ย้อนเวลาโค้ด (Code Versioning UI)
- [x] เพิ่มช่อง Chat ให้แทรกแซงกุ้ง PM
- [x] Live Cost Tracker (Gemini API cost per task/project)
- [x] Prompt Editor Modal (edit all 4 agent prompts from UI)

**Phase 5: Feature Upgrades & Polish** ✅ COMPLETED

- [x] Syntax highlighting ใน CodeViewer (Shiki, `github-dark` theme)
- [x] Task Selector UI (chip bar เปลี่ยน task บน project page)
- [x] Project Edit/Delete จาก UI (inline edit + delete confirmation)
- [x] Dashboard Error Boundaries (`error.tsx` + `global-error.tsx`)
- [x] Event-Driven SSE — แทน polling ด้วย file-based signaling (`BroadcastTaskUpdate` listener → `storage/app/sse/`)
- [x] Auto GitHub Push (`PushToGithubJob` — commit & push artifacts เมื่อ QA ผ่าน)
- [x] Live Code Preview (iframe + Tailwind CDN — preview HTML/JSX ใน CodeViewer)

**Phase 6: Advanced Features** ✅ COMPLETED

- [x] Interactive PM Review via Telegram (inline keyboard Approve/Revise + cross-channel sync)
- [x] Telegram Webhook Controller + `php artisan telegram:set-webhook`
- [x] Task Continuation (`base_task_id`) — สร้าง Task ต่อยอดจาก Task เดิม, PM/Dev ได้รับ CodeArtifacts เดิมเป็น context

**Phase 7: Agent Group Chat** ✅ COMPLETED

- [x] Agent Group Chat via Telegram — กุ้งทั้ง 4 ตัวโพสต์สถานะลง Group Chat (opt-in)

---

## 📞 Escalation Contact

เมื่อ Task มี Status `human_review_required` ให้แสดง Alert บน Dashboard
พร้อม Log ย้อนหลังทั้งหมดของ Task นั้นครับ

---

## 📱 Phase 4: Telegram Notifications & OpenClaw Integration

**Phase 4: Telegram + OpenClaw Control** ✅ COMPLETED

- [x] สร้าง `TelegramService.php` — ส่ง Notification เมื่อ Task เสร็จ/ติด/QA Fail
- [x] เพิ่ม `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` ใน `.env` และ `config/app.php`
- [x] Wire TelegramService เข้า `QaAgentJob`, `PmAgentJob`, `UxAgentJob`, `DevAgentJob`
- [x] ติดตั้ง OpenClaw Gateway เชื่อม `@NongCute_bot` กับ Gemini-powered Agent
- [x] สร้าง PowerShell Skill Scripts สำหรับควบคุม Task จาก Telegram

### Telegram Bot
- Bot: `@NongCute_bot`
- Bot Token: อยู่ใน `.env` → `TELEGRAM_BOT_TOKEN`
- Chat ID: อยู่ใน `.env` → `TELEGRAM_CHAT_ID`
- **TelegramService** → notifications only (completed/failed/human_review)
- **OpenClaw** → รับคำสั่งจาก Telegram และควบคุมระบบ

### OpenClaw Gateway
```powershell
# รัน Gateway (ต้องรันทุกครั้ง — ยังไม่ได้ install as service)
# ตั้ง GOOGLE_API_KEY ใน .env หรือ environment variable ก่อนรัน
openclaw gateway --port 18789
```
- Config: `C:\Users\Advice\.openclaw\openclaw.json`
- BOOT.md: `C:\Users\Advice\.openclaw\workspace\BOOT.md`
- Model: `google/gemini-2.5-flash` (API Key แยกจาก Backend)

### OpenClaw Skill Scripts
อยู่ที่ `C:\Users\Advice\.openclaw\workspace\skills\`:
- `list-projects.ps1` — GET /api/projects
- `create-task.ps1 -ProjectId 1 -Title "..." -Description "..." -TokenBudget 15000`
- `check-status.ps1 -TaskId 1`
- `resume-task.ps1 -TaskId 1`
- `cancel-task.ps1 -TaskId 1`
- `pm-approve.ps1 -TaskId 1` — Approve PM review from Telegram/CLI
- `pm-reply.ps1 -TaskId 1 -Message "..."` — Send revision to PM

---

## 📲 Interactive PM Review via Telegram

เมื่อ `pm_review_enabled=true` → PM Agent เสร็จ → ส่ง Telegram notification พร้อม inline buttons:

```
PM Agent → pm_review state
  → Telegram: "📋 PM Analysis for Task #N" + [✅ Approve] [✏️ Revise]
  → Dashboard: PmChatPanel (ทั้งสอง channel ทำงานพร้อมกัน)

User clicks [✅ Approve] → ux_processing → UxAgentJob
User clicks [✏️ Revise] → พิมพ์ feedback → pm_processing → PmAgentJob re-runs
```

### Key Files
- `TelegramService::notifyPmReview()` — ส่งข้อความพร้อม inline keyboard
- `TelegramWebhookController` — รับ callback_query + revision text
- `TelegramSetWebhookCommand` — `php artisan telegram:set-webhook {url}`

### Cross-Channel Sync
เมื่อ user Approve/Revise จาก Dashboard → edit Telegram message ให้แสดงว่าทำจาก Dashboard แล้ว (ป้องกัน stale buttons)

### Setup
```bash
# 1. Set webhook secret (optional, recommended)
TELEGRAM_WEBHOOK_SECRET=your-secret-here

# 2. Use ngrok for local dev
ngrok http 8000

# 3. Register webhook
php artisan telegram:set-webhook https://xxx.ngrok.io

# 4. Remove webhook when done
php artisan telegram:set-webhook --remove
```

---

## 💬 Agent Group Chat (Telegram)

น้องกุ้งทั้ง 4 ตัวจะโพสต์สถานะลง Telegram Group Chat เมื่อเริ่มและเสร็จงาน:

```
📋 กุ้ง PM เริ่มทำงาน → 📋 กุ้ง PM ทำเสร็จแล้ว!
🎨 กุ้ง UX/UI เริ่มทำงาน → 🎨 กุ้ง UX/UI ทำเสร็จแล้ว!
💻 กุ้ง Dev เริ่มทำงาน → 💻 กุ้ง Dev ทำเสร็จแล้ว!
🧪 กุ้ง QA เริ่มทำงาน → 🧪 กุ้ง QA ทำเสร็จแล้ว! / ❌ พบปัญหา
```

### Config (.env)
```env
TELEGRAM_GROUP_CHAT_ID=           # Opt-in: ใส่ Group Chat ID ถ้าต้องการ
```

### Key Methods (`TelegramService`)
- `sendToGroup()` — ส่งข้อความไปยัง group (silent skip ถ้าไม่ได้ตั้งค่า)
- `notifyAgentStart()` — แจ้งเมื่อ agent เริ่มทำงาน
- `notifyAgentComplete()` — แจ้งเมื่อ agent ทำเสร็จพร้อม summary
- `notifyAgentError()` — แจ้งเมื่อ agent เจอ error
- `notifyQaRetry()` — แจ้งเมื่อ QA fail และส่งกลับ Dev

### Design
- **Opt-in** — ถ้า `TELEGRAM_GROUP_CHAT_ID` ว่าง จะข้ามทั้งหมดโดยไม่มี error
- **Private DM ยังทำงานปกติ** — group chat เป็น channel เพิ่ม ไม่กระทบ DM เดิม
- **Non-blocking** — ใช้ try/catch + HTTP timeout 5s, ถ้า Telegram ล่ม pipeline ยังเดินต่อ

---

## 📡 SSE Architecture (Event-Driven)

SSE ใช้ file-based signaling แทน DB polling:

```
Agent Job → event(TaskStatusUpdated)
         → BroadcastTaskUpdate listener
         → writes storage/app/sse/task-{id}.json

SseController → checks signal file every 200ms (filemtime)
             → sends data to client only when file changes
             → heartbeat every 15s
             → max 5 min connection
```

- **Listener:** `app/Listeners/BroadcastTaskUpdate.php`
- **Registered in:** `AppServiceProvider::boot()`
- **Signal files:** `storage/app/sse/task-{id}.json`
- **Zero DB queries** in the SSE loop — `filemtime()` is a single OS syscall

---

## 🐙 Auto GitHub Push

เมื่อ Task ผ่าน QA สำเร็จ → `PushToGithubJob` จะ:
1. เขียน artifacts ไป `storage/app/projects/project_{id}/`
2. `git init` + `git add .` + `git commit` + `git push`
3. Log ผลลัพธ์ไว้ใน `agent_logs` (agent_type: `system`)
4. ส่ง Telegram notification

### Config (.env)
```env
GITHUB_PUSH_ENABLED=false          # เปิด/ปิด auto-push
GITHUB_REMOTE_URL=                  # e.g. https://github.com/user/repo.git
GITHUB_BRANCH=main                  # branch ที่จะ push
```

---

## 🖼️ Live Code Preview

CodeViewer มี **Preview tab** สำหรับไฟล์ `.html`, `.tsx`, `.jsx`:
- ใช้ `<iframe srcDoc>` + Tailwind CDN (ไม่ต้องติดตั้ง package เพิ่ม)
- JSX/TSX จะถูก transform เบื้องต้น: strip imports, `className` → `class`, ลบ JS expressions
- Sandbox mode: `allow-scripts` only (ปลอดภัย)

---

## 🔗 Task Continuation (base_task_id)

สร้าง Task ใหม่ที่ต่อยอดจาก Task เดิม แทนที่จะเริ่มจากศูนย์ทุกครั้ง:

```bash
# API
POST /api/tasks { "project_id": 1, "title": "Add dark mode", "base_task_id": 5 }

# OpenClaw Skill
create-task.ps1 -ProjectId 1 -Title "Add dark mode" -BaseTaskId 5
```

### How It Works
- `tasks.base_task_id` — nullable FK ชี้ไปยัง Task ต้นทาง
- **PM Agent**: ดึง CodeArtifacts (latest version) จาก base task → append เข้า prompt เป็น "existing codebase" → วางแผนแก้ไข
- **Dev Agent**: ได้รับ CodeArtifacts เดียวกัน → output เป็นไฟล์เต็ม (modified) ไม่ใช่ diff
- UX/QA ทำงานปกติ ไม่ได้รับ base task context
