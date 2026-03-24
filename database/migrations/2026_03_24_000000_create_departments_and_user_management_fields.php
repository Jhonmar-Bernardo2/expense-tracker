<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        $timestamp = now();

        $generalDepartmentId = DB::table('departments')->insertGetId([
            'name' => 'General',
            'description' => 'Default department for existing accounts.',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        Schema::table('users', function (Blueprint $table) use ($generalDepartmentId) {
            $table->enum('role', array_map(
                static fn (UserRole $role) => $role->value,
                UserRole::cases(),
            ))->default(UserRole::Staff->value)->after('password');
            $table->foreignId('department_id')
                ->default($generalDepartmentId)
                ->after('role')
                ->constrained()
                ->restrictOnDelete();
            $table->boolean('is_active')->default(true)->after('department_id');
        });

        DB::table('users')->update([
            'role' => UserRole::Staff->value,
            'department_id' => $generalDepartmentId,
            'is_active' => true,
        ]);

        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId !== null) {
            DB::table('users')
                ->where('id', $firstUserId)
                ->update(['role' => UserRole::Admin->value]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['role', 'is_active']);
        });

        Schema::dropIfExists('departments');
    }
};
