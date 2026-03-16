# Inertia Pages

This folder contains **Inertia pages** (Vue SFCs) that are directly rendered by Laravel controllers.

Convention (recommended for this app):
- Group pages by module: `Dashboard/`, `Categories/`, `Income/`, `Expenses/`, `Reports/`, `Profile/`.
- Use `Index.vue`, `Create.vue`, `Edit.vue` (and `Show.vue` only when needed).

Notes about this repo:
- Existing pages like `Dashboard.vue`, `Welcome.vue`, and the `auth/` and `settings/` folders remain as-is.
- New Expense/Budget Tracker modules should use the module-folder convention going forward.
