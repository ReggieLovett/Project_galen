<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /** Drawers a form inside them may ask to reopen. */
    private const PANELS = ['assistant', 'basket'];

    /**
     * Back to the kiosk screen the form was on (keeping its search and
     * filters), scrolled to the medicine the customer touched. Forms inside
     * a drawer send a "panel" field so the drawer opens again.
     */
    protected function backToKiosk(Request $request, ?Medicine $medicine = null): RedirectResponse
    {
        $url = url()->previous(route('kiosk'));

        if ($medicine) {
            $url .= '#medicine-'.$medicine->id;
        }

        $panel = $request->input('panel');

        return redirect()->to($url)->with('panel', in_array($panel, self::PANELS, true) ? $panel : null);
    }
}
