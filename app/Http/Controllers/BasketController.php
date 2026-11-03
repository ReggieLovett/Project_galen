<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Services\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BasketController extends Controller
{
    /**
     * ADD — put a medicine in the basket. Prescription medicines go
     * through MedicinePolicy first.
     */
    public function store(Request $request, Medicine $medicine, Cart $cart): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.config('kiosk.max_quantity_per_item')],
        ]);

        $decision = Gate::inspect('addToBasket', $medicine);

        if ($decision->denied()) {
            // The kiosk screen opens the "Prescription required" popup.
            return $this->backToKiosk($request, $medicine)->with('prescription_required', [
                'medicine_id' => $medicine->id,
                'name' => $medicine->name,
                'drug' => $medicine->drugLabel(),
                'message' => $decision->message(),
            ]);
        }

        try {
            $cart->add($medicine, $validated['quantity'] ?? 1);
        } catch (ValidationException $e) {
            return $this->backToKiosk($request, $medicine)->withErrors($e->errors());
        }

        return $this->backToKiosk($request, $medicine)->with('success', $medicine->requiresPrescription()
            ? "Prescription on file. {$medicine->name} added."
            : "{$medicine->name} added to your basket.");
    }

    /**
     * UPDATE — change how many of a basket item
     */
    public function update(Request $request, Medicine $medicine, Cart $cart): RedirectResponse
    {
        abort_unless($cart->has($medicine), 404, 'That item is not in your basket.');

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:'.config('kiosk.max_quantity_per_item')],
        ]);

        try {
            $cart->put($medicine, $validated['quantity']);
        } catch (ValidationException $e) {
            return $this->backToKiosk($request)->withErrors($e->errors());
        }

        return $this->backToKiosk($request);
    }

    /**
     * DELETE — take a medicine out of the basket
     */
    public function destroy(Request $request, Medicine $medicine, Cart $cart): RedirectResponse
    {
        $cart->remove($medicine);

        return $this->backToKiosk($request)->with('success', "{$medicine->name} removed.");
    }
}
