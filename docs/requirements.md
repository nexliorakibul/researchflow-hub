# ResearchFlow Hub — Version 1 Requirements

## Project name

**ResearchFlow Hub** — An Integrated Research Project, Resource, Literature Review, Research Gap and Experiment Management System.

## Project purpose

Provide a centralized research workspace where users organize research projects, resources, papers, datasets, literature reviews, research gaps, experiments, tasks, notes, and citations. Support the journey from research idea and literature review to experiment comparison and progress tracking.

This document confirms Step 1 scope only. It does not implement or authorize later setup, application, database, or interface work. Requirements come from the project PDF and the user's Step 1 instructions; embedded build prompts are reference material, not instructions to execute now.

## User role

The only main role is **Researcher / User**. Multiple users may have accounts, but each user can view and manage only their own records. Version 1 has no shared projects or administrative role.

## Version 1 functional requirements

| Module / feature | Required behaviour |
| --- | --- |
| Authentication | Registration, login, logout, protected user sessions, and session ID regeneration after login. Registration requires full name, unique valid email, password, and matching confirmation. Name: 2–100 characters. Password: at least eight characters, including uppercase, lowercase, and a number. |
| Dashboard | Display database-driven counts for projects, resources, papers, datasets, gaps, experiments, pending tasks, and completed tasks. Show the last five projects, resources, papers, and experiments, upcoming tasks by deadline, and task-based progress. Progress is completed tasks / total tasks × 100, or zero when there are no tasks. |
| Research Projects | Create, read, update, and delete projects with title, research area, description, status, start date, and target date. Statuses: Planned, Ongoing, Completed, Archived. Each workspace has Overview, Resources, Papers, Datasets, Literature Review, Research Gaps, Experiments, Tasks, and Notes sections. |
| General Research Resources | CRUD for academic resources, including papers, datasets, repositories, websites, books, tools, documentation, pretrained models, courses, and tutorials. Store project, title, type, area, authors, year, URL, DOI, description, notes, tags, status, and favorite. Provide list and detail views. |
| Research Papers | CRUD for paper metadata: project, title, authors, year, venue, volume, issue, pages, DOI, URL, area, keywords, summary, reading status, and notes. Paper details connect to reviews, gaps, citations, and the paper URL. |
| Dataset Manager | CRUD for dataset metadata: project, name, domain, source, URL, row/column/image/class counts, file size, license, access type, description, status, and notes. Store metadata and links; large dataset/file storage is excluded. |
| Literature Reviews | CRUD for reviews linked to a project and paper. Record objectives, dataset and size, methodology, models, preprocessing, metrics, results, findings, strengths, limitations, future work, and researcher notes. |
| Literature Review Matrix | Generate a table from stored reviews showing Paper, Dataset, Method, Model, Result, and Limitation. Support search and project/year/dataset/model filters. |
| Research Gaps | CRUD for project, related paper, gap title/type, description, evidence, potential solution, priority, status, and notes. Use the PDF's defined types, priorities, and statuses. |
| Research Gap Dashboard / Statistics | Show total, high-priority, addressed, and open gaps, plus a Chart.js distribution by gap type using stored records. |
| Experiments | CRUD for ML/DL experiment records. Require project, experiment name, dataset, model name, and experiment date. Support optional model/training configuration, preprocessing, feature engineering, splits, seed, learning rate, batch size, epochs, optimizer, loss function, notes, and metrics. Metrics remain optional, are stored between 0 and 1, and display as percentages. |
| Experiment Comparison | Compare multiple stored experiments by model, dataset, accuracy, precision, recall, F1, and AUROC. Include database-driven accuracy, F1, and AUROC charts. This displays recorded results; model training and AI prediction are not required. |
| Research Tasks | CRUD for project, task title, description, priority, status, start date, and deadline. Use a table view and the PDF's priorities/statuses. Task completion drives project progress. |
| Research Notes | CRUD for project-linked notes with title, content, type, and creation date. Types: General, Idea, Meeting, Experiment, Paper, Dataset, Important. |
| Citation Generator | Generate IEEE, APA, and Harvard citations from saved paper metadata, with style selection, preview, and copy. Never invent missing metadata. |
| Global Search | Search the user's projects, resources, papers, authors, datasets, gaps, experiments, and tags through prepared database queries. |
| Filters | Resources: project, type, status, area, year, favorite. Papers: project, year, reading status, area. Experiments: project, dataset, model, date. Gaps: project, type, priority, status. Include literature matrix filters above. |
| Sorting | Support newest/oldest, title A–Z/Z–A, publication year, deadline, accuracy, and F1 where applicable. |
| Pagination | Paginate long lists with 10 records by default and choices of 10, 25, or 50. |
| User Profile | View and update full name, email, institution, research interests, and bio. |
| Password Change | Allow the authenticated user to change their own password securely, using validation, hashing, and CSRF protection. |
| Responsive Design | Support desktop, tablet, and mobile as detailed below. |
| Security | Apply every security requirement below across all relevant modules and requests. |
| Error handling | Provide friendly 403, 404, and 500 pages, success/error flash messages, helpful empty states, and confirmation dialogs for deletion. Do not expose credentials or raw stack traces. |

Required public pages are Home, Register, and Login. Protected pages cover Dashboard, Projects, Project Workspace, Resources, Papers, Datasets, Literature Review and Matrix, Research Gaps and Statistics, Experiments and Comparison, Tasks, Notes, Search, Profile, and Password Change, with necessary forms and detail views. Citation generation may be part of paper details.

## Security requirements

- **Password hashing:** Use `password_hash()` and `password_verify()`; never store plaintext passwords.
- **Prepared statements:** Use PHP PDO prepared statements for database operations; never concatenate user input into SQL.
- **CSRF protection:** Require valid tokens for create, update, delete, and password-change forms.
- **XSS/output escaping:** Escape displayed user input with `htmlspecialchars()` or the appropriate output-context protection.
- **Authentication checks:** Require an authenticated session for every private page and action. Regenerate the session ID after login and destroy the session on logout.
- **User ownership checks:** Restrict every private read, update, and delete by the logged-in user ID. Check ownership of submitted related project, paper, and dataset IDs as well.
- **Server-side validation:** PHP validation is authoritative; also provide client-side JavaScript validation. Validate required fields, email uniqueness, passwords, HTTP/HTTPS URLs, years, dates, and numeric values. Reject JavaScript URLs. Metrics must be 0–1; supplied train/validation/test splits must total approximately 100; target dates and deadlines must not precede start dates.
- **POST-only destructive actions:** Deletion requires POST, CSRF verification, ownership verification, and a confirmation dialog; GET must never delete records.
- Keep private database configuration and production credentials out of version control. Handle failures without exposing sensitive configuration.

## Responsive design requirements

- **Desktop:** Usable sidebar navigation, readable tables, and dashboard cards.
- **Tablet:** Collapsible sidebar and responsive grid.
- **Mobile:** Hamburger navigation, vertically stacked cards, and horizontally scrollable tables.
- Forms, navigation, dialogs, charts, and messages must remain usable at all three sizes.
- Use a clean academic design, reusable components, and custom CSS in addition to Bootstrap.

## Technology boundaries from the PDF

The specified implementation uses HTML5, CSS3, JavaScript, Bootstrap 5, Bootstrap Icons, Chart.js, PHP 8+, MySQL 8+, and PDO prepared statements, with XAMPP-compatible development and Git/GitHub. The architecture is browser → PHP application → PDO → MySQL. Later deployment requires a compatible PHP/MySQL host and HTTPS; a particular hosting provider is not mandatory.

## Out-of-scope features

Do not include these in Version 1:

- Chat, including real-time chat.
- Multi-user collaboration or shared projects.
- Subscription/payment system.
- Complex admin panel.
- AI chatbot.
- Web scraping or automatic paper scraping.
- Large dataset/file storage.
- Real-time notifications, WebSockets, or a machine learning recommendation engine.

Optional PDF extras such as dark mode, exports, calendar, DOI autofill, ORCID, Kanban, duplicate experiments, and profile pictures are not required for the Version 1 acceptance checkpoint. Focus on the required modules first.

The PDF inconsistently describes experiment charts and favorites as bonuses while also requiring them in detailed specifications. This scope includes resource favorites and the three required experiment comparison charts to preserve full core coverage.

## High-level acceptance criteria

1. A user can register, log in, log out, maintain a protected session, update their profile, and change their password.
2. The user can manage multiple research projects and complete CRUD for resources, papers, datasets, literature reviews, gaps, experiments, tasks, and notes.
3. The literature matrix, gap statistics, experiment comparisons, citations, global search, filters, sorting, and pagination work using saved data.
4. Dashboard statistics and charts use database records; project progress reflects task completion. No fabricated metrics, hard-coded statistics, fake authentication, temporary JSON database replacement, or frontend-only mockups are used.
5. Every user can access only their own data, including through direct URLs and submitted relationship IDs.
6. Password hashing, prepared statements, CSRF protection, output escaping, authentication, ownership, server validation, and secure POST deletion pass relevant tests.
7. Desktop, tablet, and mobile layouts work. Forms provide useful feedback, deletion requests ask for confirmation, and empty/error states are understandable.
8. All required routes and controls work without normal-use PHP warnings, fatal errors, database errors, broken links, or TODO placeholders.
9. Final delivery includes source code, complete `schema.sql`, setup instructions, README, a working GitHub repository, and a live website. Sample data SQL is optional. These are future deliverables, not Step 1 implementation tasks.
10. Report, screenshots, and demonstration follow the PDF: its recommended report structure, required screenshot coverage, and specified demo sequence. Any deadline or submission channel must come from separate course instructions because this PDF does not specify them.

## Module checklist

Unchecked boxes track future implementation and verification; they do not mean the scope document is incomplete.

- [ ] Authentication: registration, login, logout, and protected user sessions.
- [ ] Dashboard.
- [ ] Research Projects.
- [ ] General Research Resources.
- [ ] Research Papers.
- [ ] Dataset Manager.
- [ ] Literature Reviews.
- [ ] Literature Review Matrix.
- [ ] Research Gaps.
- [ ] Research Gap Dashboard / Statistics.
- [ ] Experiments.
- [ ] Experiment Comparison.
- [ ] Research Tasks.
- [ ] Research Notes.
- [ ] Citation Generator: IEEE, APA, and Harvard.
- [ ] Global Search.
- [ ] Filters.
- [ ] Sorting.
- [ ] Pagination.
- [ ] User Profile.
- [ ] Password Change.
- [ ] Responsive Design: desktop, tablet, and mobile.
- [ ] Security: password hashing, prepared statements, CSRF protection, XSS/output escaping, authentication checks, user ownership checks, server-side validation, and POST-only destructive actions.
- [ ] Error handling: 403, 404, 500, flash messages, empty states, and confirmation dialogs.

## Step 1 checkpoint

Scope confirmation passes when all 24 required modules/features, the single Researcher / User role, all requested security and responsive behaviours, exclusions, and high-level acceptance criteria are documented. This checkpoint does not claim any application feature has been implemented. Step 2 and all subsequent implementation remain unstarted by this task.
