<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department_id',
        'is_active',
        'is_system_account',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'is_system_account' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function budgetAllocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isFinancialManagement(): bool
    {
        if ($this->department_id === null) {
            return false;
        }

        if ($this->relationLoaded('department') && $this->department !== null) {
            return $this->department->isFinancialManagement();
        }

        return $this->department()
            ->where('is_financial_management', true)
            ->exists();
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function isSystemAccount(): bool
    {
        return (bool) $this->is_system_account;
    }

    public function canManageCentralBudget(): bool
    {
        return $this->isAdmin() || $this->isFinancialManagement();
    }

    public function canManageCategoryBudgets(): bool
    {
        return ! $this->isAdmin() && $this->isFinancialManagement();
    }

    public function canRequestBudgetAllocations(): bool
    {
        return ! $this->isAdmin() && $this->isFinancialManagement();
    }

    public function canApproveTransactionRequests(): bool
    {
        return ! $this->isAdmin() && $this->isFinancialManagement();
    }

    public function canApproveBudgetAllocations(): bool
    {
        return $this->isAdmin();
    }

    public function canViewCentralBudgetPage(): bool
    {
        return $this->canManageCentralBudget();
    }

    public function canViewCentralBudgetSummaries(): bool
    {
        return $this->canManageCentralBudget();
    }
}
