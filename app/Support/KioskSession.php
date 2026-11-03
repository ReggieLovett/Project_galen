<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KioskSession
{
    /**
     * Forget the current customer: sign out the member (and any staff left
     * signed in on this device), empty the basket and issue a fresh session
     * and CSRF token for whoever touches the screen next.
     */
    public static function end(Request $request): void
    {
        Auth::guard('web')->logout();
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
