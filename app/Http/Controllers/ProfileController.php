<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountDeletionRequest;
use App\Http\Requests\LogoutOtherSessionsRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'email']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        return back()->with('status', 'profile-updated');
    }

    /** Encerra todas as sessões do usuário, exceto a atual — sem depender do
     *  middleware de "auth.session" do Fortify/Jetstream, que este app não usa. */
    public function logoutOtherSessions(LogoutOtherSessionsRequest $request): RedirectResponse
    {
        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', 'As outras sessões foram encerradas.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(AccountDeletionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $avatarPath = $user->avatar_path;

        Auth::logout();

        $user->delete();

        if ($avatarPath) {
            Storage::disk('public')->delete($avatarPath);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
