<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Resources\Settings\ProfilePageResource;
use App\Services\User\DeleteProfileService;
use App\Services\User\UpdateProfileService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', (new ProfilePageResource([
            'must_verify_email' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]))->resolve($request));
    }

    /**
     * Update the user's profile information.
     */
    public function update(
        ProfileUpdateRequest $request,
        UpdateProfileService $updateProfileService,
    ): RedirectResponse
    {
        $updateProfileService->handle($request->user(), $request->validated());

        return to_route('settings.profile.edit')->with('success', 'Profile saved.');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(
        ProfileDeleteRequest $request,
        DeleteProfileService $deleteProfileService,
    ): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $deleteProfileService->handle($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Account deleted.');
    }
}
