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

    /**
     * Display orders list with filters
     */
    public function index(Request $request)
    {
        $query = Order::with(['customer', 'orderItems']);

        // Search filter
        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('order_number', 'like', "%{$search}%")
                    ->orWhere('shopify_order_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($c) use ($search) {
                        $c->where('first_name', 'like', "%{$search}%")
                          ->orWhere('last_name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        });

        // Payment status filter
        $query->when($request->input('payment_status'), function ($q, $status) {
            $q->where('is_paid', $status === 'paid');
        });

        // Fulfillment & Shipping filters
        $query->when($request->input('fulfillment_status'), fn($q, $f) => $q->where('fulfillment_status', $f));
        $query->when($request->input('shipping_status'), fn($q, $s) => $q->where('shipping_status', $s));

        // Date filters (processed_at)
        $query->when($request->input('date_from'), fn($q, $from) => $q->whereDate('processed_at', '>=', $from));
        $query->when($request->input('date_to'), fn($q, $to) => $q->whereDate('processed_at', '<=', $to));

        // Fetch orders (limit 500)
        $orders = $query->orderByDesc('processed_at')->limit(500)->get();

        // Summary
        $summary = [
            'total_orders'  => $orders->count(),
            'total_paid'    => $orders->where('is_paid', true)->count(),
            'total_unpaid'  => $orders->where('is_paid', false)->count(),
            'total_items'   => $orders->sum(fn($o) => $o->orderItems->sum('quantity')),
            'total_revenue' => $orders->sum('total_price'),
        ];

        return view('orders.index', compact('orders', 'summary'));
    }

    /**
     * Show order details
     */
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

        // Fetch Shopify locations for fulfillment
        $locations = [];
        try {
            $locations = $this->api->getLocations();
        } catch (\Exception $e) {
            Log::error('Failed to fetch locations: ' . $e->getMessage());
        }

        return view('orders.show', compact('order', 'totalItems', 'locations'));
    }
}
