<?php

namespace App\Http\Controllers;

use App\Enums\Packaging;
use App\Models\Medicine;
use App\Services\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BasketController extends Controller
{
    /**
     * ADD — put strips and/or boxes of a medicine in the basket, e.g.
     * quantities[strip]=2&quantities[box]=1 from the chooser's +/- counters.
     * Prescription medicines go through MedicinePolicy first, before the
     * customer is asked how many they want.
     */
    public function store(Request $request, Medicine $medicine, Cart $cart): RedirectResponse
    {
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

        $validated = $request->validate([
            'quantities' => ['required', 'array:'.implode(',', array_column(Packaging::cases(), 'value'))],
            'quantities.*' => ['required', 'integer', 'min:0', 'max:'.config('kiosk.max_quantity_per_item')],
        ], [
            'quantities.required' => 'Please choose how many strips or boxes you want.',
            'quantities.array' => 'Please choose strips or boxes.',
        ]);

        $quantities = array_filter(array_map(intval(...), $validated['quantities']));

        if ($quantities === []) {
            return $this->backToKiosk($request, $medicine)->withErrors(['quantities' => 'Please choose how many strips or boxes you want.']);
        }

        try {
            $cart->addMany($medicine, $quantities);
        } catch (ValidationException $e) {
            return $this->backToKiosk($request, $medicine)->withErrors($e->errors());
        }

        $added = collect(Packaging::cases())
            ->filter(fn (Packaging $packaging) => isset($quantities[$packaging->value]))
            ->map(fn (Packaging $packaging) => $packaging->count($quantities[$packaging->value]))
            ->join(', ', ' and ')." of {$medicine->name}";

        return $this->backToKiosk($request, $medicine)->with('success', $medicine->requiresPrescription()
            ? "Prescription on file. {$added} added."
            : "{$added} added to your basket.");
    }

    /**
     * UPDATE — change how many strips or boxes of a basket item
     */
    public function update(Request $request, Medicine $medicine, Cart $cart): RedirectResponse
    {
        $validated = $request->validate([
            'packaging' => ['required', Rule::enum(Packaging::class)],
            'quantity' => ['required', 'integer', 'min:0', 'max:'.config('kiosk.max_quantity_per_item')],
        ]);

        $packaging = Packaging::from($validated['packaging']);

        abort_unless($cart->has($medicine, $packaging), 404, 'That item is not in your basket.');

        try {
            $cart->put($medicine, $packaging, $validated['quantity']);
        } catch (ValidationException $e) {
            return $this->backToKiosk($request)->withErrors($e->errors());
        }

        return $this->backToKiosk($request);
    }

    /**
     * DELETE — take the strips or the boxes of a medicine out of the basket
     */
    public function destroy(Request $request, Medicine $medicine, Cart $cart): RedirectResponse
    {
        $validated = $request->validate([
            'packaging' => ['required', Rule::enum(Packaging::class)],
        ]);

        $packaging = Packaging::from($validated['packaging']);

        $cart->remove($medicine, $packaging);

        return $this->backToKiosk($request)->with('success', "{$medicine->name} (".Str::lower($packaging->label()).') removed.');
    }
}
