# Application Services

Business logic that coordinates repositories and write operations lives here.

Conventions:
- Keep controllers thin.
- Use separate store/update services when behavior differs.
- Keep database query details inside repositories.
