const fs = require('fs');
const path = require('path');

const collectionPath = path.join(__dirname, '..', 'postman', 'CollabSpace_AI.postman_collection.json');
const collection = JSON.parse(fs.readFileSync(collectionPath, 'utf8'));

// Detailed documentation dictionary for all endpoints
const docsMap = {
    // 1. Authentication
    "Register": {
        description: "Register a new user account on the CollabSpace AI platform.\n\n### Access Control:\nPublic (No authentication required).\n\n### Request Body:\n- `name` (string, required): Full name of the user.\n- `email` (string, required, email, unique): User's primary email address.\n- `password` (string, required, min: 8): Account security password.\n- `password_confirmation` (string, required): Matching password confirmation.\n- `job_title` (string, optional): Job position title (e.g., Senior Full-Stack Engineer).\n- `years_of_experience` (integer, optional, min: 0): Total years of professional experience.\n- `phone` (string, optional): Contact mobile/phone number.\n\n### Successful Response (201 Created):\nReturns user entity, Bearer Sanctum token, and assigns default 'Team Member' role."
    },
    "Login (Admin)": {
        description: "Authenticate using Administrator credentials to receive a high-privilege Bearer API token.\n\n### Access Control:\nPublic.\n\n### Credentials:\n- `email`: `admin@collabspace.ai`\n- `password`: `password123`\n- `remember`: (boolean, optional): Extends token lifespan.\n\n### Test Script:\nAutomatically updates the collection environment variable `{{token}}` with the returned access token."
    },
    "Login (Project Manager)": {
        description: "Authenticate as Project Manager (Sarah Connor) to test PM-scoped workflows like scheduling meetings, assigning tasks, and generating AI summaries.\n\n### Access Control:\nPublic.\n\n### Credentials:\n- `email`: `pm@collabspace.ai`\n- `password`: `password123`"
    },
    "Login (Team Member)": {
        description: "Authenticate as a standard Team Member (Alex Rivera) to test member-scoped capabilities such as updating task status, sending chat messages, and viewing assigned projects.\n\n### Access Control:\nPublic.\n\n### Credentials:\n- `email`: `alex@collabspace.ai`\n- `password`: `password123`"
    },
    "Forgot Password": {
        description: "Initiate an automated password reset procedure.\n\n### Access Control:\nPublic.\n\n### Request Body:\n- `email` (string, required, email): Registered account email address.\n\n### Response (200 OK):\nSends a secure reset token via configured mail driver (or logs in local development)."
    },
    "Social Auth Redirect (Google)": {
        description: "Generate OAuth 2.0 redirection URL for third-party Single Sign-On (SSO).\n\n### Supported Providers:\n- `google`\n- `facebook`\n- `linkedin`\n\n### Response:\nHTTP 302 Redirect to provider consent screen or JSON containing redirect target."
    },
    "Social Auth Callback (Google Mock/Token)": {
        description: "Handle OAuth 2.0 provider callback code or simulate token exchange to authenticate/link user account.\n\n### Access Control:\nPublic.\n\n### Response (200 OK):\nReturns authenticated user details and Bearer token."
    },
    "Logout": {
        description: "Revoke and invalidate the current Bearer authentication token from the database.\n\n### Access Control:\nRequires `Bearer {{token}}` (auth:sanctum).\n\n### Response (200 OK):\nTokens are revoked, preventing future unauthorized usage."
    },

    // 2. Profile
    "Get Profile (/me)": {
        description: "Retrieve comprehensive profile details of the authenticated user, including active roles, assigned projects, and team memberships.\n\n### Access Control:\nRequires `Bearer {{token}}`.\n\n### Response Data:\nUser object, roles array, assigned projects with role pivots, and team roster."
    },
    "Update Profile": {
        description: "Update personal profile information for the authenticated user.\n\n### Request Body:\n- `name` (string, optional)\n- `job_title` (string, optional)\n- `years_of_experience` (integer, optional)\n- `phone` (string, optional)\n- `avatar_url` (string, url, optional)"
    },
    "Change Password": {
        description: "Securely change password for authenticated account.\n\n### Request Body:\n- `current_password` (string, required): Existing account password for verification.\n- `new_password` (string, required, min: 8): New secure password.\n- `new_password_confirmation` (string, required): Must match new_password."
    },
    "Get User Tasks": {
        description: "Retrieve all tasks specifically assigned to the currently authenticated user across all projects with status, priority, and deadline sorting.\n\n### Access Control:\nRequires `Bearer {{token}}`."
    },
    "Get User Files": {
        description: "List all files uploaded by the authenticated user across projects and task attachments.\n\n### Access Control:\nRequires `Bearer {{token}}`."
    },

    // 3. Teams
    "List Teams": {
        description: "List all workspace teams with member counts, active project counts, and leader details.\n\n### Access Control:\nAuthenticated Users.\n\n### Query Parameters:\n- `page`: Page number (default: 1)\n- `per_page`: Number of records (default: 15)\n- `search`: Filter teams by name or description."
    },
    "Create Team": {
        description: "Create a new collaborative team inside the workspace.\n\n### Access Control:\nAdministrators and Project Managers.\n\n### Request Body:\n- `name` (string, required, max: 255): Unique team title.\n- `description` (string, optional): Objectives and team domain."
    },
    "Get Team Details": {
        description: "Retrieve comprehensive details for a specific team, including member directory, individual member roles, and assigned projects."
    },
    "Update Team": {
        description: "Update team metadata (name, description).\n\n### Access Control:\nAdministrators and Team Leads."
    },
    "Assign Members to Team": {
        description: "Assign one or more users to a team with specific operational roles.\n\n### Request Body:\n- `members` (array, required): Array of member assignments.\n  - `user_id` (integer, required, exists:users,id)\n  - `role` (string, optional): One of 'Team Lead', 'Senior Member', 'Member', 'Contributor'."
    },
    "Remove Member from Team": {
        description: "Detach a specific user from the team.\n\n### URL Parameters:\n- `team`: Team ID\n- `user`: User ID to remove"
    },
    "Delete Team": {
        description: "Delete an existing team. Safely dissociates attached project relations without cascade deleting projects.\n\n### Access Control:\nAdministrators only."
    },

    // 4. Projects
    "List Projects": {
        description: "List projects accessible to the authenticated user. Admins view all projects; PMs and Members view projects they created, belong to directly, or are assigned to via teams.\n\n### Query Parameters:\n- `page`: Page index\n- `per_page`: Items per page\n- `status`: Filter by 'Planning', 'In Progress', 'On Hold', 'Completed'\n- `priority`: Filter by 'Low', 'Medium', 'High', 'Critical'\n- `search`: Keyword search in project name and description"
    },
    "Create Project": {
        description: "Initialize and create a new project with deadlines, priorities, and budget metadata.\n\n### Access Control:\nAdministrators and Project Managers.\n\n### Request Body:\n- `name` (string, required, max: 255)\n- `description` (string, optional)\n- `start_date` (date, optional, YYYY-MM-DD)\n- `deadline` (date, optional, after_or_equal:start_date)\n- `priority` (string, required: Low, Medium, High, Critical)\n- `status` (string, optional: Planning, In Progress, On Hold, Completed)"
    },
    "Get Project Overview": {
        description: "Get in-depth analytical project metrics including progress percentage, task status breakdown, team velocity, meeting counts, and file statistics."
    },
    "Get Project Teams & Task Progress (Figma Screen)": {
        description: "Dedicated endpoint matching the Figma Project Details screen: lists all assigned teams, their assigned leaders, and their specific task completion ratio formatted as 'completed/total' (e.g. '12/20')."
    },
    "Update Project": {
        description: "Update project metadata, dates, priority, or status. Note: progress percentage automatically re-calculates based on task completions."
    },
    "Assign Teams to Project": {
        description: "Associate one or multiple teams to a project.\n\n### Request Body:\n- `team_ids` (array, required): List of existing team IDs [1, 2, ...]"
    },
    "Remove Team from Project": {
        description: "Disassociate a team from a project."
    },
    "Delete Project": {
        description: "Soft/Hard delete a project along with its cascade hierarchy (tasks, meetings, messages, attachments).\n\n### Access Control:\nAdministrators and Project Creator."
    },

    // 5. Tasks & Kanban Board
    "List Tasks (with filters)": {
        description: "Filter and list workspace tasks with multi-dimensional criteria.\n\n### Query Parameters:\n- `project_id` (integer, optional)\n- `status` (string, optional: Pending, In Progress, In Review, Completed)\n- `priority` (string, optional: Low, Medium, High, Critical)\n- `assigned_to` (integer, optional)\n- `due_date` (date, optional)\n- `overdue` (boolean, optional: 1 or 0)"
    },
    "Get Kanban Board for Project": {
        description: "Retrieve project tasks grouped by status columns specifically structured for drag-and-drop Kanban boards.\n\n### Response Columns:\n- `pending`\n- `in_progress`\n- `in_review`\n- `completed`"
    },
    "Create Task": {
        description: "Create a new task within a project.\n\n### Request Body:\n- `project_id` (integer, required)\n- `name` (string, required, max: 255)\n- `description` (string, optional)\n- `priority` (string, required: Low, Medium, High, Critical)\n- `status` (string, optional, default: Pending)\n- `assigned_to` (integer, optional, exists:users,id)\n- `due_date` (date, optional, YYYY-MM-DD)"
    },
    "Get Task Details": {
        description: "Get detailed task record with assigned user, project metadata, subtasks, and attached files."
    },
    "Update Task": {
        description: "Update full task properties including assignment, priority, and dates."
    },
    "Update Task Status (Kanban Drag & Drop)": {
        description: "Rapid status transition endpoint designed for Kanban board drag-and-drop actions. Automatically updates project overall progress percentage and records completion timestamp when moved to 'Completed'.\n\n### Request Body:\n- `status` (string, required: 'Pending', 'In Progress', 'In Review', 'Completed')"
    },
    "Assign Task": {
        description: "Assign or reassign a task to a team member with automated in-app notification trigger.\n\n### Request Body:\n- `assigned_to` (integer, required, exists:users,id)"
    },
    "Delete Task": {
        description: "Delete an existing task and recalculate parent project progress percentage."
    },

    // 6. Meetings & AI Summaries
    "List Meetings (with filters)": {
        description: "List scheduled, ongoing, or past meetings with project filters and attendee rosters.\n\n### Query Parameters:\n- `project_id` (integer, optional)\n- `time_filter` (string: 'upcoming', 'past', 'today')"
    },
    "Schedule Meeting": {
        description: "Schedule a collaborative meeting with Google Meet / Zoom link, date, time slots, and attendee invites.\n\n### Request Body:\n- `project_id` (integer, required)\n- `title` (string, required)\n- `description` (string, optional)\n- `meeting_date` (date, required, YYYY-MM-DD)\n- `start_time` (time, required, HH:MM:SS)\n- `end_time` (time, required, after:start_time)\n- `meeting_link` (string, url, optional)\n- `attendee_ids` (array, optional): Array of invited user IDs."
    },
    "Get Meeting Details": {
        description: "Retrieve comprehensive meeting details, attendee attendance status, and attached AI summary."
    },
    "Update Meeting / Reschedule": {
        description: "Update meeting agenda, change dates/times, or update meeting URLs."
    },
    "Mark Attendance": {
        description: "Update attendee attendance status for a meeting.\n\n### Request Body:\n- `user_id` (integer, required)\n- `status` (string, required: 'attended', 'absent', 'excused')"
    },
    "Generate AI Meeting Summary": {
        description: "Generate structured meeting summaries, key decisions, and actionable task items using Google Gemini 1.5 Flash (with intelligent local dynamic NLP fallback if external API is unreachable).\n\n### Request Body:\n- `notes` (string, optional): Meeting minutes / transcript to summarize. If omitted, uses existing meeting notes."
    },
    "Get Meeting Summary": {
        description: "Retrieve existing AI-generated or manually refined meeting summary."
    },
    "Update Meeting Summary Manually": {
        description: "Allows project managers to manually edit and approve the AI summary, decisions, or action items."
    },
    "Convert Summary Action Items to Kanban Tasks": {
        description: "One-click automation that converts the AI-generated action items from the meeting summary into actual Kanban tasks inside the parent project, automatically assigning default priorities and dates."
    },
    "Delete Meeting": {
        description: "Cancel and delete a scheduled meeting."
    },

    // 7. File Management
    "List Files (with filters)": {
        description: "Browse uploaded assets and attachments across projects or tasks.\n\n### Query Parameters:\n- `project_id` (integer, optional)\n- `task_id` (integer, optional)\n- `search` (string, optional)"
    },
    "Upload File to Project": {
        description: "Upload a file attached directly to a project.\n\n### Form Data:\n- `file` (binary file, required, max: 20MB)\n- `project_id` (integer, required)\n- `name` (string, optional)"
    },
    "Upload File to Task": {
        description: "Upload an attachment specifically bound to a task.\n\n### Form Data:\n- `file` (binary file, required)\n- `task_id` (integer, required)"
    },
    "Get File Details": {
        description: "Retrieve file metadata, formatted human-readable size (e.g. '1.5 MB'), MIME type, and uploader profile."
    },
    "Download File": {
        description: "Stream download file content securely through an authenticated binary response."
    },
    "Delete File": {
        description: "Permanently delete file from server storage and remove database attachment record."
    },

    // 8. Real-Time Project Chat
    "List Project Messages": {
        description: "Fetch paginated chat messages for a specific project with threaded parent-child relations, file attachments, and sender profiles."
    },
    "Send Message (with Mentions & Attachment)": {
        description: "Broadcast and store a new chat message in the project stream. Instantly broadcasts via WebSockets (`Laravel Reverb`) to `private-projects.{id}` channel and generates notifications for mentioned users.\n\n### Request Body:\n- `message` (string, required)\n- `parent_id` (integer, optional): Reply to a specific thread message\n- `file_attachment_id` (integer, optional): Attach an existing uploaded file\n- `mentions` (array, optional): User IDs tagged with @mention"
    },
    "Send Thread Reply": {
        description: "Post a targeted reply within an existing conversation thread (`parent_id`)."
    },
    "Edit Message": {
        description: "Edit existing message text (sets `is_edited = true`). Allowed only by message author or Administrator."
    },
    "Delete Message": {
        description: "Delete a message from the project chat stream."
    },
    "Mark All Messages as Read": {
        description: "Bulk mark all unread messages in a project as read for the authenticated user."
    },
    "Get Unread Messages Count": {
        description: "Get real-time total count of unread project messages for badge counters."
    },

    // 9. Notifications
    "List User Notifications": {
        description: "Retrieve all in-app notifications (task assignments, mentions, meeting invites) with pagination."
    },
    "List Unread Notifications": {
        description: "Fetch only unread notifications requiring user attention."
    },
    "Get Unread Notifications Count": {
        description: "Fast count endpoint for displaying unread notification indicator dot."
    },
    "Mark Notification as Read": {
        description: "Mark a single notification as read by its UUID."
    },
    "Mark All Notifications as Read": {
        description: "Mark all user notifications as read in a single batch operation."
    },
    "Delete Notification": {
        description: "Dismiss and delete a specific notification."
    },

    // 10. Dashboard & AI Assistant
    "Get Dashboard Overview": {
        description: "Executive workspace dashboard matching the 100% exact Figma UI schema. Returns aggregated statistics, yearly timeline graphs, active projects with task ratios, recent tasks, teams, upcoming meetings, files, and activity logs."
    },
    "Ask AI Assistant (General Workspace Q&A)": {
        description: "Engage in an AI-powered conversational query regarding overall workspace productivity, ongoing deadlines, and health metrics.\n\n### Request Body:\n- `prompt` (string, required): Question or command for the AI assistant.\n- `project_id` (integer, optional): Scope query to a single project."
    },
    "Ask AI Assistant (Project-Scoped)": {
        description: "Ask the AI assistant specific questions regarding a single project's deliverables and roadblocks."
    },
    "Ask AI Assistant (Overdue Tasks Audit)": {
        description: "Command the AI assistant to analyze overdue tasks and recommend priority actions."
    },

    // 11. Reports Module
    "Project Reports": {
        description: "Aggregated reporting on projects: completion rates, budget/deadline health, and average velocity."
    },
    "Task Reports & Productivity": {
        description: "Comprehensive task completion statistics, overdue ratios, and priority distributions."
    },
    "Team Performance Reports": {
        description: "Performance metrics across all teams: tasks delivered per team, member velocity, and active commitments."
    },
    "Personal Productivity Report": {
        description: "Calculates personal productivity score (0 - 100%) for the authenticated user based on completed tasks, on-time submissions, and contributions."
    },
    "Member Productivity Report (Admin/PM)": {
        description: "Inspect individual member productivity score and historical performance metrics. Accessible to Admins and Project Managers."
    },

    // 12. User Management (Admin)
    "List Users": {
        description: "List all registered workspace users with role names, active statuses, and experience details.\n\n### Access Control:\nAdministrators only."
    },
    "Create User (Admin Only)": {
        description: "Directly create and onboard a new user with pre-assigned roles and status.\n\n### Access Control:\nAdministrators only.\n\n### Request Body:\n- `name`, `email`, `password`, `job_title`, `role`, `status`"
    },
    "Show User Details": {
        description: "View comprehensive user record with assigned projects, tasks, and audit logs."
    },
    "Update User (Admin Only)": {
        description: "Update user profile, status ('active' / 'inactive'), or change role assignment.\n\n### Access Control:\nAdministrators only."
    },
    "Delete User (Admin Only)": {
        description: "Soft/Hard delete a user account from the workspace.\n\n### Access Control:\nAdministrators only."
    },

    // 13. System Administration & Compliance
    "Get AI Configuration Settings": {
        description: "View global AI engine configuration: active provider (Gemini / Fallback), model version, temperature, and enabled AI capabilities."
    },
    "Update AI Settings (Admin Only)": {
        description: "Configure system-wide AI parameters, change model identifiers, and adjust creativity temperature (0.0 - 1.0).\n\n### Access Control:\nAdministrators only."
    },
    "List Audit Logs (Admin/PM)": {
        description: "Retrieve comprehensive security and compliance audit trail: user logins, entity mutations, permission modifications, and IP addresses."
    }
};

// Traverse and apply descriptions
let updatedCount = 0;
collection.item.forEach(folder => {
    if (folder.item) {
        folder.item.forEach(reqObj => {
            const doc = docsMap[reqObj.name];
            if (doc) {
                reqObj.request.description = doc.description;
                updatedCount++;
            } else {
                reqObj.request.description = `Endpoint: ${reqObj.name}\n\nMethod: ${reqObj.request.method}\nURL: ${reqObj.request.url.raw || ''}`;
                updatedCount++;
            }
        });
    }
});

fs.writeFileSync(collectionPath, JSON.stringify(collection, null, 4), 'utf8');
console.log(`Successfully enriched ${updatedCount} endpoints with comprehensive documentation!`);
