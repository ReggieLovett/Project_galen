<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Staff sign-in for the hidden admin area. Uses the "admin" guard, so it
 * never touches the kiosk's member sign-in.
 */
class AuthController extends Controller
{
    /**
     * LOGIN - show the staff login page
     */
    public function showLogin(): View
    {
        return view('admin.login');
    }

    /**
     * LOGIN - check the email and password
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // No "remember me": the kiosk is a shared, public device.
        if (Auth::guard('admin')->attempt($credentials)) {
            $request->session()->regenerate(); // new session id after login

            Auth::guard('admin')->user()->forceFill(['last_login_at' => now()])->save();

            return redirect()->intended(route('admin.dashboard'));
        }

        return back()
            ->withErrors(['email' => __('auth.failed')])
            ->onlyInput('email');
    }

    /**
     * LOGOUT - ends the session
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate(); // throws away the session data
        $request->session()->regenerateToken(); // regenerate CSRF token

        return to_route('admin.login')->with('success', 'You have been logged out.');
    }
}
