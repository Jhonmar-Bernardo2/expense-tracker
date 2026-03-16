# Expense / Budget Tracker - Recommended Build Steps

Use this sequence to keep dependencies clear and avoid jumping ahead.

1. Project structure and architectural conventions
2. Database schema, migrations, models, relationships, and casts
3. Authentication and shared Inertia props
4. Category module
5. Budget module
6. Transactions module
7. Dashboard
8. Reports
9. Charts
10. Profile
11. Seeders
12. UI polish
13. Final review and refactor

Notes:
- Budgets should be implemented before dashboard and reports because both depend on monthly budget summaries.
- Each step should include: files to create, why they are needed, the implementation, and relevant tests.
