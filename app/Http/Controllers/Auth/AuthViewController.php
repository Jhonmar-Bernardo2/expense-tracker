<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class AuthViewController extends Controller
{
    public function login(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function register(): Response
    {
        return Inertia::render('auth/Register');
    }

    public function forgotPassword(Request $request): Response
    {
        return Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function resetPassword(Request $request, string $token): Response
    {
        return Inertia::render('auth/ResetPassword', [
            'email' => (string) $request->input('email', ''),
            'token' => $token,
        ]);
    }

    public function verifyEmail(Request $request): Response
    {
        return Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function twoFactorChallenge(): Response
    {
        return Inertia::render('auth/TwoFactorChallenge');
    }

    public function confirmPassword(): Response
    {
        return Inertia::render('auth/ConfirmPassword');
    }
}

