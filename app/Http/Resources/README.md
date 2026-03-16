# HTTP Resources

This folder contains **Laravel JSON Resources** used to transform backend models/aggregates into
frontend-ready data for **Inertia** page props.

Guidelines:
- Keep transformations here (formatting, shaping, computed fields), not in Controllers.
- Prefer explicit "page-ready" structures over leaking raw Eloquent models.
- Avoid database queries in Resources; fetch needed relations via Repositories.

Typical files (examples):
- `CategoryResource.php`
- `IncomeResource.php`
- `ExpenseResource.php`
- `MonthlySummaryResource.php`
- `YearlySummaryResource.php`
