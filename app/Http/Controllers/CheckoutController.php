<?php

namespace App\Http\Controllers;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Medicine;
use App\Models\Order;
use App\Services\Cart;
use App\Support\KioskSession;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    /**
     * STORE — turn the basket into an order the customer pays for at the
     * counter, then wipe the session so the next customer starts fresh.
     */
    public function store(Request $request, Cart $cart): RedirectResponse
    {
        if ($cart->isEmpty()) {
            return back()->withErrors(['basket' => 'Your basket is empty.']);
        }

        // One checkout at a time across every kiosk. SQLite ignores
        // lockForUpdate(), and the daily order number is "count + 1", so two
        // kiosks finishing together could oversell the last pack or reuse a number.
        try {
            $order = Cache::lock('kiosk:checkout', 10)->block(5, fn () => DB::transaction(fn () => $this->placeOrder($request, $cart)));
        } catch (LockTimeoutException) {
            return back()->withErrors(['basket' => 'The till is busy. Please tap Place order again.']);
        }

        KioskSession::end($request);

        return redirect()->to(URL::temporarySignedRoute('receipt', now()->addMinutes(15), ['order' => $order->order_number]));
    }

    /**
     * @throws ValidationException when an item sold out or needs a prescription
     */
    private function placeOrder(Request $request, Cart $cart): Order
    {
        $quantities = $cart->quantities();

        $medicines = Medicine::with('category')->whereKey(array_keys($quantities))->orderBy('name')->get();

        // Re-check everything: stock may have sold out, and the basket must
        // never hold a prescription item this customer can't buy.
        foreach ($medicines as $medicine) {
            if (Gate::denies('addToBasket', $medicine)) {
                throw ValidationException::withMessages([
                    'basket' => "{$medicine->name} needs a prescription on file. Please remove it or ask at the counter.",
                ]);
            }

            if ($medicine->stock < $quantities[$medicine->id]) {
                throw ValidationException::withMessages([
                    'basket' => "Sorry, only {$medicine->stock} of {$medicine->name} left. Please update your basket.",
                ]);
            }
        }

        $order = Order::create([
            'order_number' => Order::nextKioskNumber(),
            'user_id' => $request->user()?->id,
            'channel' => OrderChannel::Kiosk,
            'status' => OrderStatus::AwaitingPayment,
            'item_count' => array_sum($quantities),
            'total' => $medicines->sum(fn (Medicine $medicine) => $medicine->price * $quantities[$medicine->id]),
        ]);

        foreach ($medicines as $medicine) {
            $quantity = $quantities[$medicine->id];

            $order->items()->create([
                'medicine_id' => $medicine->id,
                'name' => $medicine->name,
                'brand' => $medicine->brand,
                'strength' => $medicine->strength,
                'unit_price' => $medicine->price,
                'quantity' => $quantity,
                'line_total' => $medicine->price * $quantity,
            ]);

            // Held for this order until the cashier completes or cancels it.
            $medicine->decrement('stock', $quantity);
        }

        return $order;
    }
}
