<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): View
    {
        if ($request->query('next') === 'plan') {
            $request->session()->put('url.intended', route('plan', ['step' => 8]));
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    /**
     * Log out and show the login page, returning to the page that needed another account
     * (only paths on this site; anything else goes to the admin dashboard).
     */
    public function switchAccount(Request $request): RedirectResponse
    {
        $next = (string) $request->input('next', '');
        $next = str_starts_with($next, '/') && ! str_starts_with($next, '//') ? url($next) : route('admin.dashboard');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('url.intended', $next);

        return redirect()->route('login')->with('status', 'Log in with your staff account.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
