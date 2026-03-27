<?php

use App\Services\Department\FinancialManagementDepartmentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->boolean('is_financial_management')->default(false)->after('description');
            $table->boolean('is_locked')->default(false)->after('is_financial_management');
        });

        app(FinancialManagementDepartmentService::class)->bootstrapCentralBudgetWorkflow();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn(['is_financial_management', 'is_locked']);
        });
    }
};
