<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Services\TwoFactorAuthenticationService;
use App\Support\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('two_factor.user_id')) {
            return redirect()->route('login');
        }

        return view('auth.login', ['mode' => 'desafio']);
    }

    public function store(TwoFactorChallengeRequest $request, TwoFactorAuthenticationService $service): RedirectResponse
    {
        $user = $request->verify($service);
        $remember = (bool) $request->session()->pull('two_factor.remember', false);
        $request->session()->forget('two_factor.user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();
        SecurityAudit::log(
            $user,
            'acesso',
            $request->boolean('recovery') ? 'Login com código de recuperação' : 'Login com verificação em duas etapas',
            $request,
        );

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
