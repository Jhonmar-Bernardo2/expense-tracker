<?php

use App\Http\Controllers\Auth\AuthViewController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
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
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('transactions', TransactionController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('reports', [ReportsController::class, 'index'])
        ->name('reports.index');

    Route::middleware('admin')->group(function () {
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
