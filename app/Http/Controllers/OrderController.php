<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Services\ApiServices;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected ApiServices $api;

    public function __construct(ApiServices $api)
    {
        $this->api = $api;
    }

// ======================
//   List and filter orders
// ======================   
    public function index(Request $request)
    {
        $query = Order::with(['customer', 'orderItems', 'refunds']);

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%$search%")
                  ->orWhere('shopify_order_id', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhereHas('customer', fn($c) => 
                        $c->where('first_name', 'like', "%$search%")
                          ->orWhere('last_name', 'like', "%$search%")
                          ->orWhere('email', 'like', "%$search%")
                  );
            });
        }

        // Status filters
        if ($status = $request->input('payment_status')) {
            $query->where('is_paid', $status === 'paid');
        }
        if ($fulfillment = $request->input('fulfillment_status')) {
            $query->where('fulfillment_status', $fulfillment);
        }
        if ($from = $request->input('date_from')) {
            $query->whereDate('processed_at', '>=', $from);
        }
        if ($to = $request->input('date_to')) {
            $query->whereDate('processed_at', '<=', $to);
        }

        $orders = $query->orderByDesc('processed_at')->limit(500)->get();

        // Summary using Report-style revenue calculation
        $totalRevenue = 0;
        $totalItems = 0;

        foreach ($orders as $order) {
            $refunds = $order->refunds->sum('total_amount');
            $totalRevenue += max($order->subtotal_price - $order->total_discounts - $refunds, 0);
            $totalItems += $order->orderItems->sum('quantity');
        }

        $summary = [
            'total_orders'  => $orders->count(),
            'total_paid'    => $orders->where('is_paid', true)->count(),
            'total_unpaid'  => $orders->where('is_paid', false)->count(),
            'total_items'   => $totalItems,
            'total_revenue' => $totalRevenue,
        ];

        return view('orders.index', compact('orders', 'summary'));
    }

// ======================
//   Show order details
// ======================   
    public function show(int $id)
    {
        $order = Order::with([
            'customer',
            'orderItems',
            'payments',
            'fulfillments',
            'refunds.refundItems.orderItem',
            'refunds.orderAdjustments'
        ])->findOrFail($id);

        $totalItems = $order->orderItems->sum('quantity');

       
        try {
            $locations = $this->api->getLocations();
        } catch (\Exception $e) {
            Log::error('Failed to fetch locations: ' . $e->getMessage());
        }

        // Calculate revenue
        $refunds = $order->refunds->sum('total_amount');
        $totalRevenue = max($order->subtotal_price - $order->total_discounts - $refunds, 0);

        return view('orders.show', compact('order', 'totalItems', 'locations', 'totalRevenue'));
    }
}
