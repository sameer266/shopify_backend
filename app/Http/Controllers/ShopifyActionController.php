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
    // Fulfillment
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

            // Give Shopify webhook time to sync
            sleep(2);

            return redirect()->route('orders.show', $order->id)->with('success', 'Order fulfilled successfully.');
        } catch (\Exception $e) {
            Log::error('Fulfillment failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return redirect()->route('orders.show', $order->id)->with('error', 'Fulfillment failed: ' . $e->getMessage());
        }
    }

    // ==========================================
    //  Order Manipulation (GraphQL)
    // ==========================================

    public function cancelOrder(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        try {
            $this->api->cancelOrder($order->shopify_order_id);
            
            // Give Shopify webhook time to sync
            sleep(2);
            
            return redirect()->route('orders.show', $order->id)->with('success', 'Order cancelled successfully.');
        } catch (\Exception $e) {
            Log::error('Cancel order failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return redirect()->route('orders.show', $order->id)->with('error', 'Cancellation failed: ' . $e->getMessage());
        }
    }

    public function updateOrderQuantity(Request $request, $orderId)
    {
        $request->validate([
            'line_item_id' => 'required|integer',
            'quantity' => 'required|integer|min:0',
        ]);

        $order = Order::findOrFail($orderId);
        $lineItem = $order->orderItems()->findOrFail($request->line_item_id);

        // Convert order ID to Shopify GID
        $orderGID = "gid://shopify/Order/{$order->shopify_order_id}";

        try {
            // 1️⃣ Begin order edit and fetch all line items
            $beginEdit = $this->api->graphQL('
                mutation orderEditBegin($id: ID!) {
                    orderEditBegin(id: $id) {
                        calculatedOrder {
                            id
                            lineItems(first: 100) {
                                edges {
                                    node {
                                        id
                                        variant {
                                            id
                                        }
                                        quantity
                                    }
                                }
                            }
                        }
                        userErrors {
                            field
                            message
                        }
                    }
                }
            ', ['id' => $orderGID]);

            Log::info('Begin Edit Response:', ['response' => $beginEdit]);

            $beginEditData = $beginEdit['orderEditBegin'] ?? null;
            
            if (!$beginEditData) {
                throw new \Exception("Invalid response from orderEditBegin: " . json_encode($beginEdit));
            }

            if (!empty($beginEditData['userErrors'])) {
                $errors = collect($beginEditData['userErrors'])
                    ->pluck('message')->join(', ');
                throw new \Exception("Begin Order Edit Error: {$errors}");
            }

            $calculatedOrderId = $beginEditData['calculatedOrder']['id'];
            
            // Find the line item and check its current quantity
            $calculatedLineItemId = "gid://shopify/CalculatedLineItem/{$lineItem->shopify_line_item_id}";
            
            $calculatedLineItems = $beginEditData['calculatedOrder']['lineItems']['edges'];
            $currentQuantity = null;
            $variantId = null;
            
            foreach ($calculatedLineItems as $edge) {
                if ($edge['node']['id'] === $calculatedLineItemId) {
                    $currentQuantity = $edge['node']['quantity'];
                    $variantId = $edge['node']['variant']['id'];
                    break;
                }
            }

            if ($currentQuantity === null) {
                throw new \Exception("Line item not found in calculated order");
            }

            Log::info('Line item status:', [
                'calculated_line_item_id' => $calculatedLineItemId,
                'current_quantity' => $currentQuantity,
                'new_quantity' => $request->quantity,
                'variant_id' => $variantId,
            ]);

            // If current quantity is 0 (removed), we need to add the variant back
            if ($currentQuantity == 0 && $request->quantity > 0) {
                Log::info('Line item is removed, adding variant back');
                
                $addVariant = $this->api->graphQL('
                    mutation orderEditAddVariant($id: ID!, $variantId: ID!, $quantity: Int!) {
                        orderEditAddVariant(id: $id, variantId: $variantId, quantity: $quantity) {
                            calculatedLineItem {
                                id
                                quantity
                            }
                            calculatedOrder {
                                id
                            }
                            userErrors {
                                field
                                message
                            }
                        }
                    }
                ', [
                    'id' => $calculatedOrderId,
                    'variantId' => $variantId,
                    'quantity' => (int) $request->quantity,
                ]);

                Log::info('Add Variant Response:', ['response' => $addVariant]);

                $addVariantData = $addVariant['orderEditAddVariant'] ?? null;
                
                if (!$addVariantData) {
                    throw new \Exception("Invalid response from orderEditAddVariant: " . json_encode($addVariant));
                }

                if (!empty($addVariantData['userErrors'])) {
                    $errors = collect($addVariantData['userErrors'])
                        ->pluck('message')->join(', ');
                    throw new \Exception("Add Variant Error: {$errors}");
                }
            } 
            // If we're setting quantity to 0, use orderEditSetQuantity to remove it
            elseif ($request->quantity == 0) {
                Log::info('Setting quantity to 0 (removing item)');
                
                $updateQty = $this->api->graphQL('
                    mutation orderEditSetQuantity($id: ID!, $lineItemId: ID!, $quantity: Int!) {
                        orderEditSetQuantity(id: $id, lineItemId: $lineItemId, quantity: $quantity) {
                            calculatedLineItem {
                                id
                                quantity
                            }
                            calculatedOrder {
                                id
                            }
                            userErrors {
                                field
                                message
                            }
                        }
                    }
                ', [
                    'id' => $calculatedOrderId,
                    'lineItemId' => $calculatedLineItemId,
                    'quantity' => 0,
                ]);

                Log::info('Update Quantity Response:', ['response' => $updateQty]);

                $updateQtyData = $updateQty['orderEditSetQuantity'] ?? null;
                
                if (!$updateQtyData) {
                    throw new \Exception("Invalid response from orderEditSetQuantity: " . json_encode($updateQty));
                }

                if (!empty($updateQtyData['userErrors'])) {
                    $errors = collect($updateQtyData['userErrors'])
                        ->pluck('message')->join(', ');
                    throw new \Exception("Set Quantity Error: {$errors}");
                }
            }
            // Normal case: update existing quantity
            else {
                Log::info('Updating existing line item quantity');
                
                $updateQty = $this->api->graphQL('
                    mutation orderEditSetQuantity($id: ID!, $lineItemId: ID!, $quantity: Int!) {
                        orderEditSetQuantity(id: $id, lineItemId: $lineItemId, quantity: $quantity) {
                            calculatedLineItem {
                                id
                                quantity
                            }
                            calculatedOrder {
                                id
                            }
                            userErrors {
                                field
                                message
                            }
                        }
                    }
                ', [
                    'id' => $calculatedOrderId,
                    'lineItemId' => $calculatedLineItemId,
                    'quantity' => (int) $request->quantity,
                ]);

                Log::info('Update Quantity Response:', ['response' => $updateQty]);

                $updateQtyData = $updateQty['orderEditSetQuantity'] ?? null;
                
                if (!$updateQtyData) {
                    throw new \Exception("Invalid response from orderEditSetQuantity: " . json_encode($updateQty));
                }

                if (!empty($updateQtyData['userErrors'])) {
                    $errors = collect($updateQtyData['userErrors'])
                        ->pluck('message')->join(', ');
                    throw new \Exception("Set Quantity Error: {$errors}");
                }
            }

            // 3️⃣ Commit the order edit
            $commit = $this->api->graphQL('
                mutation orderEditCommit($id: ID!, $notifyCustomer: Boolean, $staffNote: String) {
                    orderEditCommit(
                        id: $id
                        notifyCustomer: $notifyCustomer
                        staffNote: $staffNote
                    ) {
                        order {
                            id
                        }
                        userErrors {
                            field
                            message
                        }
                    }
                }
            ', [
                'id' => $calculatedOrderId,
                'notifyCustomer' => false,
                'staffNote' => 'Quantity updated via admin panel'
            ]);

            Log::info('Commit Response:', ['response' => $commit]);

            $commitData = $commit['orderEditCommit'] ?? null;
            
            if (!$commitData) {
                throw new \Exception("Invalid response from orderEditCommit: " . json_encode($commit));
            }

            if (!empty($commitData['userErrors'])) {
                $errors = collect($commitData['userErrors'])
                    ->pluck('message')->join(', ');
                throw new \Exception("Commit Order Edit Error: {$errors}");
            }

            // Give Shopify webhook time to sync
            sleep(2);

            return redirect()->route('orders.show', $order->id)->with('success', 'Order quantity updated successfully.');
        } catch (\Exception $e) {
            Log::error('Order quantity update failed: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'line_item_id' => $request->line_item_id,
                'quantity' => $request->quantity,
            ]);
            
            return redirect()->route('orders.show', $order->id)->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    // ==========================================
    //  Refund (REST)
    // ==========================================

    public function createRefund(Request $request, $orderId)
    {
        $request->validate([
            'refund_items' => 'required|array',
            'refund_shipping' => 'nullable|boolean',
            'location_id' => 'required',
        ]);

        $order = Order::findOrFail($orderId);
        $refundLineItems = [];

        foreach ($request->refund_items as $item) {
            if ($item['quantity'] > 0) {
                $orderItem = $order->orderItems()->find($item['id']);
                if ($orderItem) {
                    $refundLineItems[] = [
                        'line_item_id' => $orderItem->shopify_line_item_id,
                        'quantity' => (int) $item['quantity'],
                        'restock_type' => 'return',
                    ];
                }
            }
        }

        if (empty($refundLineItems) && empty($request->refund_shipping)) {
            return redirect()->route('orders.show', $order->id)->with('error', 'No items selected for refund.');
        }

        try {
            $this->api->createRefund(
                $order->shopify_order_id,
                $refundLineItems,
                $request->boolean('refund_shipping'),
                $request->location_id,
                false  // notify_customer
            );

            // Give Shopify webhook time to sync
            sleep(2);

            return redirect()->route('orders.show', $order->id)->with('success', 'Refund created successfully.');
        } catch (\Exception $e) {
            Log::error('Refund failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return redirect()->route('orders.show', $order->id)->with('error', 'Refund failed: ' . $e->getMessage());
        }
    }
}
