# Expense / Budget Tracker - Project Structure

This document describes the recommended structure for this Laravel 11 + Inertia + Vue 3 app.

## Goals

Modules:
- Authentication
- Dashboard
- Budget Management
- Category Management
- Income Management
- Expense Management
- Reports
- Profile

## Backend (Laravel)

### Controllers (thin)
Path: `app/Http/Controllers/`

Responsibilities:
- Authorize (policies/gates)
- Validate with Form Requests
- Call a service
- Return an Inertia response or redirect with flash data

### Requests (validation)
Path: `app/Http/Requests/`

Responsibilities:
- Validation rules for store, update, and index/filter forms
- Input normalization where appropriate
- No business logic

### Services (business logic)
Path: `app/Services/`

Responsibilities:
- Orchestrate write operations and business rules
- Use repositories for persistence/query access
- Keep separate services for store vs update flows

### Repositories (queries)
Path: `app/Repositories/`

Responsibilities:
- Encapsulate Eloquent queries, filtering, eager loading, and aggregates
- Scope all user-owned data to the authenticated user

### Resources (frontend-ready data)
Path: `app/Http/Resources/`

Responsibilities:
- Transform models and aggregates into the exact shape the Vue pages need
- Keep derived frontend fields centralized

## Frontend (Vue 3 + Inertia)

### Pages
Path: `resources/js/pages/`

Responsibilities:
- Each page corresponds to a controller action returning `Inertia::render()`
- Pages use Inertia forms and redirect flows instead of Axios

Recommended convention:
- `Dashboard.vue` or `Dashboard/Index.vue`
- `Categories/Index.vue`
- `Budgets/Index.vue`
- `Transactions/Index.vue`
- `Reports/Index.vue`
- `settings/Profile.vue`

### Layouts
Path: `resources/js/layouts/`

Responsibilities:
- Shared shell for authenticated and guest experiences
- Sidebar, header, and settings layouts

### Shared components
Path: `resources/js/components/`

Responsibilities:
- App-level reusable components like navigation, flash messages, charts, and helpers

### shadcn-vue components
Path: `resources/js/components/ui/`

Responsibilities:
- UI primitives from shadcn-vue such as Button, Dialog, Input, Select, Card, Table

## How Laravel + Inertia + Vue + Wayfinder fit together

Request/response flow:
1. A Vue page submits via `useForm()` or the Inertia router.
2. Laravel routes to a thin controller.
3. The controller validates through a dedicated Form Request.
4. The controller calls a service for writes or a repository for read-focused page data.
5. Laravel returns `Inertia::render()` or a redirect with flash data.
6. Inertia updates the Vue page props without a separate API layer.

Wayfinder:
- Wayfinder generates typed route/action helpers for the frontend.
- Vue pages and components use those helpers instead of hardcoded URLs.

## Data ownership

All user-owned entities must be scoped to the authenticated user:
- Categories
- Budgets
- Transactions
- Reports and dashboard aggregates

Enforce ownership in repositories, validation rules, and any record lookup paths.
