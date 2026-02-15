<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
   

// ======================
//   Webhook handling
// ======================
    protected function handle(Request $request)
    {
        $body   = $request->getContent();
        $hmac   = $request->header('X-Shopify-Hmac-Sha256', '');
        $secret = env('SHOPIFY_WEBHOOK_SECRET');

        Log::info('Webhook received', [
            'hmac' => $hmac,
            'length' => strlen($body),
            'topic' => $request->header('X-Shopify-Topic')
        ]);

        // Verify webhook secret is configured
        if (!$secret) {
            Log::error('Webhook secret missing');
            return response('Webhook secret missing', 401);
        }

        // Verify HMAC signature
        $calculated = base64_encode(
            hash_hmac('sha256', $body, $secret, true)
        );

        if (!hash_equals($calculated, $hmac)) {
            Log::warning('Invalid HMAC signature', [
                'expected' => $calculated,
                'received' => $hmac
            ]);
            return response('Invalid HMAC', 401);
        }

        // Decode payload
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            Log::error('Invalid payload - not valid JSON');
            return response('Invalid payload', 400);
        }

        // Process the order
        try {
            app(SyncController::class)->saveOrder($payload);
            
            Log::info('Webhook processed successfully', [
                'order_number' => $payload['order_number'] ?? 'N/A',
                'order_id' => $payload['id'] ?? 'N/A'
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Webhook order sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_id' => $payload['id'] ?? 'N/A'
            ]);
            return response('Webhook error', 500);
        }

        return response('OK', 200);
    }

  
    // ======================
    //   Order deletion handling
    // ======================
    public function deleteOrder(Request $request)
    {
        $body   = $request->getContent();
        $hmac   = $request->header('X-Shopify-Hmac-Sha256', '');
        $secret = env('SHOPIFY_WEBHOOK_SECRET');

        Log::info('Delete order webhook received', [
            'hmac' => $hmac,
            'length' => strlen($body)
        ]);

        // Verify webhook secret
        if (!$secret) {
            return response('Webhook secret missing', 401);
        }

        // Verify HMAC
        $calculated = base64_encode(
            hash_hmac('sha256', $body, $secret, true)
        );

        if (!hash_equals($calculated, $hmac)) {
            return response('Invalid HMAC', 401);
        }

        // Decode payload
        $payload = json_decode($body, true);
        
        Log::info('Delete order payload', $payload);

        // Delete the order if it exists
        if (!empty($payload['id'])) {
            $order = Order::where('shopify_order_id', $payload['id'])->first();
            
            if ($order) {
                // Delete related records
                $order->orderItems()->delete();
                $order->fulfillments()->delete();
                $order->payments()->delete();
                $order->refunds()->each(function ($refund) {
                    $refund->refundItems()->delete();
                    $refund->delete();
                });
                $order->delete();
                
                Log::info('Order deleted successfully', [
                    'shopify_order_id' => $payload['id']
                ]);
            } else {
                Log::info('Order not found for deletion', [
                    'shopify_order_id' => $payload['id']
                ]);
            }
        }

        return response('OK', 200);
    }

    

    // ======================
    //   Webhook endpoints Orders Create
    // ======================
    public function ordersCreate(Request $request)
    {
        return $this->handle($request);
    }

    // ======================
    //   Webhook endpoints Orders Update
    // ======================
    public function ordersUpdate(Request $request)
    {
        return $this->handle($request);
    }

    // ======================
    //   Webhook endpoints Orders Cancel
    // ======================
    public function ordersCancel(Request $request)
    {
        return $this->handle($request);
    }

 
    // ======================
    //   Webhook endpoints Orders Delete
    // ======================
    public function orderDelete(Request $request)
    {
        return $this->deleteOrder($request);
    }
}
