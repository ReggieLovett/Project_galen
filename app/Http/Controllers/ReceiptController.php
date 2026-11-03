<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

class ReceiptController extends Controller
{
    /**
     * The order slip the customer takes to the cashier. Reached only through
     * the signed link checkout returns, so order numbers can't be browsed.
     */
    public function show(Order $order): View
    {
        $barcode = (new SvgRenderer)
            ->setSvgType(SvgRenderer::TYPE_SVG_INLINE)
            ->render((new TypeCode128)->getBarcode($order->order_number), 360, 88);

        return view('receipt', [
            'order' => $order->load('items.medicine.category', 'user'),
            'barcode' => $barcode,
        ]);
    }
}
