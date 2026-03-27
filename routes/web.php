<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\App\ApprovalVoucherAttachmentController;
use App\Http\Controllers\App\ApprovalVoucherController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\NotificationController;
use App\Http\Controllers\App\ReportsController;
use App\Http\Controllers\App\TransactionController;
use App\Http\Controllers\Auth\AuthViewController;
use App\Http\Controllers\Finance\BudgetController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome')->name('home');
Route::redirect('/dashboard', '/app/dashboard');

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

    Route::redirect('/profile', '/settings/profile');
});

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::prefix('app')->name('app.')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::patch('read-all', [NotificationController::class, 'readAll'])->name('read-all');
            Route::patch('{notification}/read', [NotificationController::class, 'read'])->name('read');
        });

        Route::resource('transactions', TransactionController::class)
            ->only(['index']);

        Route::resource('approval-vouchers', ApprovalVoucherController::class)
            ->parameters(['approval-vouchers' => 'approvalVoucher'])
            ->only(['index', 'show', 'store', 'update']);

        Route::get('approval-vouchers/{approvalVoucher}/print', [ApprovalVoucherController::class, 'print'])
            ->name('approval-vouchers.print');

        Route::get('approval-vouchers/{approvalVoucher}/download', [ApprovalVoucherController::class, 'download'])
            ->name('approval-vouchers.download');

        Route::get(
            'approval-vouchers/{approvalVoucher}/attachments/{attachment}/download',
            [ApprovalVoucherAttachmentController::class, 'download'],
        )->name('approval-vouchers.attachments.download');

        Route::post('approval-vouchers/{approvalVoucher}/submit', [ApprovalVoucherController::class, 'submit'])
            ->name('approval-vouchers.submit');

        Route::patch('approval-vouchers/{approvalVoucher}/approve', [ApprovalVoucherController::class, 'approve'])
            ->name('approval-vouchers.approve');

        Route::patch('approval-vouchers/{approvalVoucher}/reject', [ApprovalVoucherController::class, 'reject'])
            ->name('approval-vouchers.reject');

        Route::get('reports', [ReportsController::class, 'index'])
            ->name('reports.index');
    });

    Route::prefix('finance')->name('finance.')->group(function () {
        Route::resource('budgets', BudgetController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
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
