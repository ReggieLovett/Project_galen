<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderChannel;
use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\Order;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Staff landing page: kiosk orders waiting at the till and stock alerts.
     */
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'awaitingPayment' => Order::with('user', 'items.medicine.category')
                ->awaitingPayment()
                ->latest()
                ->limit(25)
                ->get(),
            'kioskOrdersToday' => Order::where('channel', OrderChannel::Kiosk)->whereDate('created_at', today())->count(),
            'outOfStock' => Medicine::with('category')->outOfStock()->orderBy('name')->get(),
            'lowStock' => Medicine::with('category')
                ->lowStock()
                ->orderBy('stock')
                ->get(),
        ]);
    }
}
