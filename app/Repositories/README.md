# Repositories

This folder contains database query logic used by Services/Controllers.

Guidelines:
- Keep Eloquent query building here (filters, eager loads, aggregates).
- **Always scope user-owned data to the authenticated user** (e.g., `where('user_id', $userId)`).
- Keep method names intent-revealing (e.g., `paginateForUser`, `findForUserOrFail`).

Typical files (examples):
- `CategoryRepository.php`
- `IncomeRepository.php`
- `ExpenseRepository.php`
- `ReportRepository.php`
