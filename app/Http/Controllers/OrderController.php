<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

use App\Services\ApiServices;

class OrderController extends Controller
{
    protected $api;

    public function __construct(ApiServices $api)
    {
        $this->api = $api;
    }
    // Display orders list with filters
    public function index(Request $request)
    {
        $query = Order::with('customer', 'orderItems');

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('shopify_order_id', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($c) use ($search) {
                      $c->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Payment status filter
        if ($status = $request->input('payment_status')) {
            $query->where('is_paid', $status === 'paid');
        }

        // Fulfillment & Shipping
        if ($fulfillment = $request->input('fulfillment_status')) {
            $query->where('fulfillment_status', $fulfillment);
        }

        if ($shipping = $request->input('shipping_status')) {
            $query->where('shipping_status', $shipping);
        }

        // Date filters (use Shopify order date: processed_at)
        if ($from = $request->input('date_from')) {
            $query->whereDate('processed_at', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('processed_at', '<=', $to);
        }

        // Get orders (limit 500) ordered by processed_at
        $orders = $query->orderByDesc('processed_at')->limit(500)->get();

        // Summary
        $summary = [
            'total_orders'   => $orders->count(),
            'total_paid'     => $orders->where('is_paid', true)->count(),
            'total_unpaid'   => $orders->where('is_paid', false)->count(),
            'total_items'    => $orders->sum(fn($o) => $o->orderItems->sum('quantity')),
            'total_revenue'  => $orders->sum('total_price'),
        ];

        return view('orders.index', compact('orders', 'summary'));
    }

    // Show order details
    public function show($id)
    {
        $order = Order::with(['customer', 'orderItems', 'payments', 'fulfillments', 'refunds.refundItems.orderItem', 'refunds.orderAdjustments'])->findOrFail($id);
        
        $totalItems = $order->orderItems->sum('quantity');
        
        // Fetch locations for fulfillment modal
        $locations = [];
        try {
            $locations = $this->api->getLocations();
        } catch (\Exception $e) {
            // Log error but don't break the page
            \Log::error('Failed to fetch locations: ' . $e->getMessage());
        }

        return view('orders.show', compact('order', 'totalItems', 'locations'));
    }
}
