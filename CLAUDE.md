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
| Realtime | Server-Sent Events (SSE) (With Auto-Reconnect) |
| Extras | ZIP Export (Code Artifacts) |

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
- `tasks` — งานย่อย (มี `token_budget`, `token_used`, `retry_count`)
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
│   │   ├── Services/
│   │   │   ├── StateMachineService.php
│   │   │   └── HtmlValidatorService.php  ← ตัวตรวจโครงสร้าง HTML จาก Ollama
│   │   ├── Jobs/              ← Queue Jobs ของกุ้งแต่ละตัว
│   │   └── Http/Controllers/
│   └── database/migrations/
└── frontend/                  ← Next.js 14
    └── src/
        ├── components/
        │   ├── KanbanBoard.tsx
        │   ├── AgentStatusCard.tsx
        │   ├── CodeViewer.tsx
        │   └── AgentOutputPanel.tsx
        └── app/
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
- Bot Token: `8579700752:AAHT0KDhSQ8LMW66uWiRBNISLl3jOqe2Y3M` (อยู่ใน `.env`)
- Chat ID: `8768795349` (อยู่ใน `.env`)
- **TelegramService** → notifications only (completed/failed/human_review)
- **OpenClaw** → รับคำสั่งจาก Telegram และควบคุมระบบ

### OpenClaw Gateway
```powershell
# รัน Gateway (ต้องรันทุกครั้ง — ยังไม่ได้ install as service)
$env:GOOGLE_API_KEY="AIzaSyCP_T5nuXmSq-psUX_DEk0dHXVPna2LJ64"
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
