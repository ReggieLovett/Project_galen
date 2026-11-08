<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The cashier's side of a kiosk order. Each action only applies to an
 * order still awaiting payment, checked in the same query that changes it,
 * so two tills can't both handle one slip.
 */
class OrderController extends Controller
{
    /**
     * The customer paid. From now on the order counts as purchase history,
     * so its prescription items can be refilled at the kiosk.
     */
    public function complete(Order $order): RedirectResponse
    {
        $updated = Order::whereKey($order->id)
            ->awaitingPayment()
            ->update(['status' => OrderStatus::Completed, 'completed_at' => now()]);

        return $updated
            ? back()->with('success', "Order {$order->order_number} marked as paid.")
            : $this->alreadyHandled($order);
    }

    /**
     * The customer didn't pay or changed their mind: put the stock that
     * checkout reserved back on the shelf.
     */
    public function cancel(Order $order): RedirectResponse
    {
        $cancelled = DB::transaction(function () use ($order) {
            $updated = Order::whereKey($order->id)
                ->awaitingPayment()
                ->update(['status' => OrderStatus::Cancelled]);

            if (! $updated) {
                return false;
            }

            foreach ($order->items as $item) {
                Medicine::whereKey($item->medicine_id)->increment('stock', $item->piece_count);
            }

            return true;
        });

        return $cancelled
            ? back()->with('success', "Order {$order->order_number} cancelled. Its items are back in stock.")
            : $this->alreadyHandled($order);
    }

    private function alreadyHandled(Order $order): RedirectResponse
    {
        $status = Str::lower($order->fresh()->status->label());

        return back()->with('error', "Order {$order->order_number} was already {$status}.");
    }
}
