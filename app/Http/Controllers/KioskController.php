<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Medicine;
use App\Services\Assistant\Conversation;
use App\Services\Cart;
use App\Support\KioskSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KioskController extends Controller
{
    /**
     * WELCOME — the idle "Touch to begin" screen
     */
    public function welcome(): View
    {
        return view('welcome');
    }

    /**
     * START — a new customer touched the screen: begin with a clean session
     */
    public function start(Request $request): RedirectResponse
    {
        KioskSession::end($request);

        return to_route('kiosk');
    }

    /**
     * INDEX — the shopping screen: search, browse, basket and assistant
     */
    public function index(Request $request, Cart $cart, Conversation $conversation): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'type' => ['nullable', Rule::in([Category::OTC, Category::PRESCRIPTION])],
            'class' => ['nullable', 'string', 'max:60'],
        ]);

        $medicines = Medicine::with('category')
            ->search($filters['q'] ?? null)
            ->ofType($filters['type'] ?? null)
            ->inClass($filters['class'] ?? null)
            ->orderBy('name')
            ->get();

        return view('kiosk', [
            'medicines' => $medicines,
            'filters' => $filters,
            'classes' => Medicine::query()->distinct()->orderBy('therapeutic_class')->pluck('therapeutic_class'),
            'outOfStock' => Medicine::outOfStock()->orderBy('name')->get(),
            'basket' => $cart->lines(),
            'basketQuantities' => $cart->quantities(),
            'basketTotal' => $cart->total(),
            'basketCount' => $cart->count(),
            'conversation' => $conversation->entries(),
        ]);
    }

    /**
     * RESET — "Start over", or the idle timer ran out
     */
    public function reset(Request $request): RedirectResponse
    {
        KioskSession::end($request);

        return to_route('welcome');
    }
}
