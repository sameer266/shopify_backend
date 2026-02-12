<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ApiServices;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class ShopifyActionController extends Controller
{
    protected $api;

    public function __construct(ApiServices $api)
    {
        $this->api = $api;
    }

    // ==========================================
    // 1️⃣ Fulfillment
    // ==========================================

    public function createFulfillment(Request $request, $orderId)
    {
        $request->validate([
            'tracking_number' => 'nullable|string',
            'tracking_company' => 'nullable|string',
        ]);

        $order = Order::findOrFail($orderId);

        try {
            $this->api->createFulfillment(
                $order->shopify_order_id,
                $request->tracking_number,
                $request->tracking_company
            );

            return back()->with('success', 'Order fulfilled successfully in Shopify.');
        } catch (\Exception $e) {
            return back()->with('error', 'Fulfillment failed: ' . $e->getMessage());
        }
    }

    // ==========================================
    // 2️⃣ Order Manipulation (GraphQL)
    // ==========================================

    public function cancelOrder(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        try {
            $this->api->cancelOrder($order->shopify_order_id);
            return back()->with('success', 'Order cancelled successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Cancellation failed: ' . $e->getMessage());
        }
    }

    public function updateOrderQuantity(Request $request, $orderId)
    {
        $request->validate([
            'line_item_id' => 'required',
            'quantity' => 'required|integer|min:0',
        ]);

        $order = Order::findOrFail($orderId);
        // Find the line item to get its Shopify ID
        $lineItem = $order->orderItems()->findOrFail($request->line_item_id);

        try {
            $this->api->updateOrderQuantity(
                $order->shopify_order_id,
                $lineItem->shopify_line_item_id,
                (int) $request->quantity
            );

            return back()->with('success', 'Order quantity updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    // ==========================================
    // 3️⃣ Refund (REST)
    // ==========================================

    public function createRefund(Request $request, $orderId)
    {
        $request->validate([
            'refund_items' => 'required|array', // e.g. [{id: 1, quantity: 1}]
            'refund_shipping' => 'nullable|boolean',
            'location_id' => 'required', // Location required for restock
        ]);

        $order = Order::findOrFail($orderId);
        $refundLineItems = [];

        foreach ($request->refund_items as $item) {
            if ($item['quantity'] > 0) {
                // Map local ID back to Shopify Line Item ID
                $orderItem = $order->orderItems()->find($item['id']);
                if ($orderItem) {
                    $refundLineItems[] = [
                        'line_item_id' => $orderItem->shopify_line_item_id,
                        'quantity' => (int) $item['quantity'],
                        'restock_type' => 'return', // Default to return to inventory
                    ];
                }
            }
        }

        if (empty($refundLineItems) && empty($request->refund_shipping)) {
             return back()->with('error', 'No items selected for refund.');
        }

        try {
            $this->api->createRefund(
                $order->shopify_order_id,
                $refundLineItems,
                $request->boolean('refund_shipping'),
                $request->location_id
            );

            return back()->with('success', 'Refund created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Refund failed: ' . $e->getMessage());
        }
    }
}
