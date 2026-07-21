<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->syncRoleConstraint([
            UserRole::Admin->value,
            UserRole::Finance->value,
            UserRole::Staff->value,
        ]);

        DB::table('users')
            ->join('departments', 'departments.id', '=', 'users.department_id')
            ->where('departments.is_financial_management', true)
            ->where('users.role', UserRole::Staff->value)
            ->update([
                'users.role' => UserRole::Finance->value,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('role', UserRole::Finance->value)
            ->update([
                'role' => UserRole::Staff->value,
            ]);

        $this->syncRoleConstraint([
            UserRole::Admin->value,
            UserRole::Staff->value,
        ]);
    }

    /**
     * @param  list<string>  $roles
     */
    private function syncRoleConstraint(array $roles): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $allowedRoles = implode(', ', array_map(
                static fn (string $role) => "'{$role}'",
                $roles,
            ));

            DB::unprepared(<<<SQL
DO $$
DECLARE constraint_name text;
BEGIN
    SELECT c.conname
    INTO constraint_name
    FROM pg_constraint c
    INNER JOIN pg_class t ON t.oid = c.conrelid
    WHERE t.relname = 'users'
      AND c.contype = 'c'
      AND pg_get_constraintdef(c.oid) LIKE '%role%';

    IF constraint_name IS NOT NULL THEN
        EXECUTE format('ALTER TABLE users DROP CONSTRAINT %I', constraint_name);
    END IF;

    ALTER TABLE users ALTER COLUMN role TYPE varchar(255);
    ALTER TABLE users ALTER COLUMN role SET NOT NULL;
    ALTER TABLE users ALTER COLUMN role SET DEFAULT 'staff';
    ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ({$allowedRoles}));
END $$;
SQL);

            return;
        }

        if ($driver === 'mysql') {
            $allowedRoles = implode(', ', array_map(
                static fn (string $role) => "'{$role}'",
                $roles,
            ));

            DB::statement(
                "ALTER TABLE users MODIFY role ENUM({$allowedRoles}) NOT NULL DEFAULT 'staff'",
            );
        }
    }
};
