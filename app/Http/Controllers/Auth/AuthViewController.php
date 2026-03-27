<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\ForgotPasswordPageResource;
use App\Http\Resources\Auth\LoginPageResource;
use App\Http\Resources\Auth\ResetPasswordPageResource;
use App\Http\Resources\Auth\VerifyEmailPageResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class AuthViewController extends Controller
{
    public function login(Request $request): Response
    {
        return Inertia::render('auth/Login', (new LoginPageResource([
            'can_reset_password' => Features::enabled(Features::resetPasswords()),
            'can_register' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]))->resolve($request));
    }

    public function register(): Response
    {
        return Inertia::render('auth/Register');
    }

    public function forgotPassword(Request $request): Response
    {
        return Inertia::render('auth/ForgotPassword', (new ForgotPasswordPageResource([
            'status' => $request->session()->get('status'),
        ]))->resolve($request));
    }

    public function resetPassword(Request $request, string $token): Response
    {
        return Inertia::render('auth/ResetPassword', (new ResetPasswordPageResource([
            'email' => (string) $request->input('email', ''),
            'token' => $token,
        ]))->resolve($request));
    }

    public function verifyEmail(Request $request): Response
    {
        return Inertia::render('auth/VerifyEmail', (new VerifyEmailPageResource([
            'status' => $request->session()->get('status'),
        ]))->resolve($request));
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
