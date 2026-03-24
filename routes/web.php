<?php

use App\Http\Controllers\Auth\AuthViewController;
use App\Http\Controllers\ApprovalVoucherController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthViewController::class, 'login'])->name('login');

    if (Features::enabled(Features::registration())) {
        Route::get('/register', [AuthViewController::class, 'register'])->name('register');
    }

    if (Features::enabled(Features::resetPasswords())) {
        Route::get('/forgot-password', [AuthViewController::class, 'forgotPassword'])->name('password.request');
        Route::get('/reset-password/{token}', [AuthViewController::class, 'resetPassword'])->name('password.reset');
    }

    Route::get('/two-factor-challenge', [AuthViewController::class, 'twoFactorChallenge'])->name('two-factor.login');
});

Route::middleware(['auth', 'active'])->group(function () {
    if (Features::enabled(Features::emailVerification())) {
        Route::get('/email/verify', [AuthViewController::class, 'verifyEmail'])->name('verification.notice');
    }

    Route::get('/confirm-password', [AuthViewController::class, 'confirmPassword'])->name('password.confirm');

    Route::redirect('/profile', '/settings/profile')->name('profile');
});

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('budgets', BudgetController::class)
        ->only(['index']);

    Route::resource('transactions', TransactionController::class)
        ->only(['index']);

    Route::resource('approval-vouchers', ApprovalVoucherController::class)
        ->parameters(['approval-vouchers' => 'approvalVoucher'])
        ->only(['index', 'show', 'store', 'update']);

    Route::post('approval-vouchers/{approvalVoucher}/submit', [ApprovalVoucherController::class, 'submit'])
        ->name('approval-vouchers.submit');

    Route::resource('vouchers', VoucherController::class)
        ->only(['index', 'show', 'store', 'update']);

    Route::post('vouchers/{voucher}/submit', [VoucherController::class, 'submit'])
        ->name('vouchers.submit');

    Route::post('vouchers/{voucher}/liquidation', [VoucherController::class, 'submitLiquidation'])
        ->name('vouchers.liquidation.submit');

    Route::get('vouchers/{voucher}/attachments/{attachment}', [VoucherController::class, 'downloadAttachment'])
        ->name('vouchers.attachments.download');

    Route::get('reports', [ReportsController::class, 'index'])
        ->name('reports.index');

    Route::middleware('admin')->group(function () {
        Route::patch('approval-vouchers/{approvalVoucher}/approve', [ApprovalVoucherController::class, 'approve'])
            ->name('approval-vouchers.approve');

        Route::patch('approval-vouchers/{approvalVoucher}/reject', [ApprovalVoucherController::class, 'reject'])
            ->name('approval-vouchers.reject');

        Route::patch('vouchers/{voucher}/approve', [VoucherController::class, 'approve'])
            ->name('vouchers.approve');

        Route::patch('vouchers/{voucher}/reject', [VoucherController::class, 'reject'])
            ->name('vouchers.reject');

        Route::patch('vouchers/{voucher}/release', [VoucherController::class, 'release'])
            ->name('vouchers.release');

        Route::patch('vouchers/{voucher}/liquidation/return', [VoucherController::class, 'returnLiquidation'])
            ->name('vouchers.liquidation.return');

        Route::patch('vouchers/{voucher}/liquidation/approve', [VoucherController::class, 'approveLiquidation'])
            ->name('vouchers.liquidation.approve');

        Route::resource('categories', CategoryController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::resource('departments', DepartmentController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::resource('users', UserController::class)
            ->only(['index', 'store', 'update']);

        Route::patch('users/{user}/status', [UserController::class, 'updateStatus'])
            ->name('users.status.update');
    });
});

require __DIR__.'/settings.php';
