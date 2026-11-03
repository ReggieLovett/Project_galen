<?php

namespace App\Policies;

use App\Models\Medicine;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MedicinePolicy
{
    public const PRESCRIPTION_REQUIRED = 'Prescription required. Please proceed to the pharmacy counter for assistance.';

    /**
     * Over-the-counter medicines are open to everyone. Prescription medicines
     * only go in the basket when the signed-in member has bought this drug
     * before; guests are always sent to the counter.
     */
    public function addToBasket(?User $user, Medicine $medicine): Response
    {
        if (! $medicine->requiresPrescription()) {
            return Response::allow();
        }

        if ($user?->hasPurchasedBefore($medicine)) {
            return Response::allow();
        }

        return Response::deny(self::PRESCRIPTION_REQUIRED, 'prescription_required');
    }
}
