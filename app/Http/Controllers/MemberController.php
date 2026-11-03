<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\User;
use App\Services\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Mock identification: tapping a loyalty card signs the member in, no
 * password. A real kiosk would read the card's NFC chip or barcode here.
 */
class MemberController extends Controller
{
    /**
     * SIGN IN — find the member by card number and log them in
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_number' => ['required', 'string', 'max:20'],
            'add' => ['nullable', 'integer'],
        ]);

        $member = User::where('member_number', trim($validated['member_number']))->first();

        if (! $member) {
            return back()
                ->withErrors(['member_number' => 'We could not find that member card. Check the number or ask at the counter.'])
                ->onlyInput('member_number');
        }

        Auth::login($member);
        $request->session()->regenerate(); // new session id after login

        // Signed in from the "Prescription required" popup: go back to that medicine.
        if ($medicine = Medicine::find($validated['add'] ?? null)) {
            return $this->backToKiosk($request, $medicine)
                ->with('success', "Welcome back, {$member->firstName()}. Tap Add to basket on {$medicine->name} again.");
        }

        return $this->backToKiosk($request)->with('success', "Welcome back, {$member->firstName()}.");
    }

    /**
     * SIGN OUT — keep shopping as a guest. Refills that relied on the
     * member's history leave the basket.
     */
    public function destroy(Request $request, Cart $cart): RedirectResponse
    {
        Auth::logout();
        $request->session()->regenerate();

        $removed = $cart->removeItemsNotAllowedFor(null);

        return $this->backToKiosk($request)->with('success', $removed->isEmpty()
            ? 'Signed out. You can keep shopping as a guest.'
            : 'Signed out. '.$removed->pluck('name')->join(', ', ' and ').' left your basket because it needs your prescription history.');
    }
}
