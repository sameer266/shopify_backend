<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
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

        // Date filters
        if ($from = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Get orders (limit 500)
        $orders = $query->orderByDesc('created_at')->limit(500)->get();

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
    public function show(Order $order_id)
    {
       $order=Order::with('customer', 'orderItems')->findOrFail($order_id->id);
       $order->load('customer', 'orderItems');
       

        $totalItems = $order->orderItems->sum('quantity');

        return view('orders.show', compact('order', 'totalItems'));
    }
}
