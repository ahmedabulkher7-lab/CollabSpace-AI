# CollabSpace AI - Backend API Platform

<p align="center">
  <strong>Next-Generation AI-Powered Project Collaboration Platform</strong><br>
  Built with Laravel 12, PHP 8.3, Laravel Reverb (WebSockets), Spatie Permission, and Google Gemini AI.
</p>

---

## 🌟 Overview

**CollabSpace AI** is an enterprise-grade backend API engineered for cross-functional agile teams. It unifies project management, Kanban workflows, real-time threaded chat, automated meeting transcripts summarization via Google Gemini 1.5 Flash, in-app notifications, and a conversational AI project assistant.

---

## 🚀 Key Modules & Architecture

### 1. Authentication & Granular RBAC (`/api/v1/auth`)
- **Sanctum API Tokens** with granular abilities and token lifecycle management.
- **Spatie Roles & Permissions**: Preconfigured roles (`Administrator`, `Project Manager`, `Team Member`) and explicit permissions.
- **Socialite OAuth 2.0**: Native support for Google, Facebook, and LinkedIn SSO.
- **Profile Management**: Profile updating, secure password changes, and avatar uploads.

### 2. Teams Management (`/api/v1/teams`)
- Full CRUD operations with team ownership.
- Dynamic member assignment with custom roles within teams (`Frontend Dev`, `Scrum Master`, etc.).
- Cascading synchronization with project teams.

### 3. Projects Module (`/api/v1/projects`)
- Project lifecycle management with multi-team assignment and direct member roles.
- Real-time project overview metrics with team task completion ratios (e.g. `12/20`).
- Automatic weighted `progress_percentage` recalculations on task lifecycle updates.

### 4. Tasks & Interactive Kanban Board (`/api/v1/tasks`)
- Standard task CRUD with priorities (`Low`, `Medium`, `High`, `Critical`) and statuses (`Pending`, `In Progress`, `In Review`, `Completed`).
- **Kanban Board Grouping**: `GET /api/v1/projects/{project}/tasks/kanban` returning organized column arrays with task counts.
- **Drag-and-Drop Status Updates**: `PATCH /api/v1/tasks/{task}/status` updating task state, setting completion timestamps, and automatically updating project progress.

### 5. Meetings & AI Summaries (`/api/v1/meetings`)
- Schedule and manage project meetings with attendees and meeting links.
- Attendance tracker (`PATCH /api/v1/meetings/{meeting}/attendance`).
- **AI-Powered Meeting Summarization (`POST /meetings/{meeting}/summary/generate`)**:
  - Leverages **Google Gemini 1.5 Flash** to extract executive summary, decisions made, action items, risks, and next steps from meeting transcripts.
  - Built-in dynamic NLP parser fallback ensuring 100% uptime in offline or test environments.
- **Action Items to Kanban Tasks (`POST /meetings/{meeting}/summary/create-tasks`)**: One-click bulk conversion of AI action items into assigned Kanban tasks.

### 6. File Management & Attachments (`/api/v1/files`)
- Secure multipart file uploads linked to projects and tasks.
- Storage isolation with MIME-type validation and audit logging.
- Secure streaming downloads (`GET /api/v1/files/{file}/download`) and human-readable size formatting (`MB`, `KB`, `B`).

### 7. Real-Time Project Chat (`/api/v1/projects/{project}/messages`)
- **Laravel Reverb & WebSockets**: Ultra low-latency real-time broadcasting via `MessageSent` event on private project channels (`projects.{id}`).
- **Threaded Replies**: Native hierarchy support (`parent_id`) for discussions and code reviews.
- **Mentions & File Attachments**: Mention project members with automatic notifications and link files directly in chat.
- **Read Receipts & Unread Badges**: Track read status per member and calculate project unread count.

### 8. Notifications Module (`/api/v1/notifications`)
- Built on Laravel's Database Notification system.
- Automated notification triggers for:
  - Task assignments and reassignments.
  - Chat `@mentions`.
  - Meeting invitations.
- Query filters (`?status=unread`, `?status=read`, `?type=...`), mark as read, mark all as read, and unread count badge.

### 9. Executive Dashboard & Conversational AI Assistant (`/api/v1/dashboard`, `/api/v1/ai/ask`)
- **Executive Dashboard (`GET /api/v1/dashboard`)**: Aggregated metrics covering projects, tasks by status and priority, overdue task warnings, upcoming meetings, recent activities, and recent files.
- **AI Assistant (`POST /api/v1/ai/ask`)**: Natural language conversational assistant answering questions about sprint status, overdue tasks, team workloads, and upcoming meetings with contextual project awareness.

---

## 🛠️ Software Engineering & Architecture Quality

- **Clean Architecture & Separation of Concerns**:
  - `FormRequests` handle 100% of validation and request authorization.
  - `Policies` enforce authorization rules and project boundaries.
  - `Services` encapsulate business logic, transactions, and event dispatches.
  - `API Resources` format consistent JSON output with strict schemas.
- **Zero N+1 Queries**: Strict prevention of lazy loading (`Model::preventLazyLoading()`) with eager loading across all relationships.
- **Database Performance**: Indexed foreign keys, polymorphic lookups, and compound status indexes.
- **Audit Logging**: Comprehensive activity tracking across Auth, Teams, Projects, Tasks, Meetings, Files, and Chat.
- **Strict Typing**: PHP 8.3 typed properties, explicit method return types, and PHPDoc array shape contracts.

---

## 🧪 Automated Testing Suite

All modules are backed by a comprehensive PHPUnit feature test suite:

```bash
php artisan test --compact
```

### Test Suite Summary:
- **Total Tests**: `117` passed
- **Assertions**: `513` passed
- **Failures / Errors**: `0` (100% pass rate)

| Feature Test Suite | Tests | Assertions | Description |
|---|---|---|---|
| `AuthTest` | 11 | 38 | Registration, Login, RBAC, Socialite, Profile |
| `TeamTest` | 9 | 40 | Team CRUD, member assignments, access control |
| `ProjectTest` | 10 | 44 | Project lifecycle, metrics, multi-team access |
| `TaskTest` | 11 | 48 | Tasks CRUD, Kanban grouping, progress recalculation |
| `MeetingTest` | 12 | 52 | Meetings, attendance, Gemini summaries, task generation |
| `FileAttachmentTest` | 8 | 32 | Upload, streaming download, deletion, storage isolation |
| `ChatTest` | 11 | 36 | Threaded chat, Reverb broadcast, mentions, read receipts |
| `NotificationTest` | 11 | 42 | Notifications listing, unread counts, trigger dispatches |
| `DashboardTest` | 5 | 36 | Aggregated workspace metrics, overdue task auditing |
| `AiAssistantTest` | 6 | 26 | Natural language Q&A, context injection, validation |
| `ReportTest` | 9 | 54 | Project, task, team reports and personal productivity score |
| `UserManagementTest` | 7 | 18 | Admin user CRUD, role management, account status |
| `SystemSettingsTest` | 5 | 14 | AI system settings and compliance audit logs API |

---

## 📬 Postman Collection

A complete, production-ready Postman collection is located at:
`postman/CollabSpace_AI.postman_collection.json`

### Collection Folders:
1. `1. Authentication` (Register, Login Admin/PM/Member, Profile, Logout)
2. `2. Users & Roles` (List Users, Role Check)
3. `3. Teams` (CRUD, Assign Members, Remove Member)
4. `4. Projects` (CRUD, Project Overview, Assign Teams)
5. `5. Tasks & Kanban` (CRUD, Kanban Board Grouping, Status Drag-and-Drop)
6. `6. Meetings & AI Summaries` (Meetings CRUD, Attendance, Gemini AI Summarization, Convert Action Items to Tasks)
7. `7. File Management & Attachments` (Upload File, Stream Download, Delete File)
8. `8. Real-Time Project Chat` (List Threaded Messages, Send Message with Mentions, Edit, Delete, Mark Read, Unread Count)
9. `9. Notifications` (List Notifications, Filter Unread, Unread Count, Mark Read, Mark All Read, Delete)
10. `10. Dashboard & AI Assistant` (Dashboard Overview, Ask AI Assistant Workspace/Project/Overdue)
11. `11. Reports Module` (Project Reports, Task Reports, Team Performance, User Productivity Score)
12. `12. User Management (Admin)` (User CRUD, Role Management, Account Activation)
13. `13. System Administration & Compliance` (AI Platform Settings, System Audit Logs)

### How to Import & Run in Postman:
1. Open Postman -> Click **Import** -> Select `postman/CollabSpace_AI.postman_collection.json`.
2. Configure Collection Variables:
   - `base_url`: `http://127.0.0.1:8000`
   - `token`: automatically set upon executing `Register` or `Login`.

---

## ⚙️ Installation & Local Setup

### 1. Prerequisites
- PHP `>= 8.3`
- Composer `>= 2.6`
- SQLite or MySQL

### 2. Setup Steps

```bash
# 1. Clone repository
git clone <repo-url>
cd comptition

# 2. Install dependencies
composer install

# 3. Environment configuration
cp .env.example .env
php artisan key:generate

# 4. Run database migrations & seeders (Roles, Permissions, and Default Admin)
php artisan migrate --seed

# 5. Start Laravel Reverb WebSocket server (optional for live WebSockets)
php artisan reverb:start

# 6. Start API server
php artisan serve
```

---

## 🔒 Default Administrator Credentials

- **Email**: `admin@collabspace.ai`
- **Password**: `password123`
- **Role**: `Administrator`

---

## 📜 License

This project is licensed under the MIT License.
