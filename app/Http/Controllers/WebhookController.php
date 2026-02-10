<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected function handle(Request $request)
    {
        $body   = $request->getContent();
        $hmac   = $request->header('X-Shopify-Hmac-Sha256', '');
        $secret = env('SHOPIFY_WEBHOOK_SECRET');


        Log::info('Webhook received', ['hmac' => $hmac, 'length' => strlen($body)]);

        if (!$secret) {
            return response('Webhook secret missing', 401);
        }

        $calculated = base64_encode(
            hash_hmac('sha256', $body, $secret, true)
        );

        if (!hash_equals($calculated, $hmac)) {
            return response('Invalid HMAC', 401);
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return response('Invalid payload', 400);
        }

        try {
            app(SyncController::class)->replaceOrder($payload);
        } catch (\Throwable $e) {
        } catch (\Throwable $e) {
            Log::error('Webhook order sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response('Webhook error', 500);
        }

        return response('OK', 200);
    }



public function deleteOrder(Request $request)
{
    $body   = $request->getContent();
    $hmac   = $request->header('X-Shopify-Hmac-Sha256', '');
    $secret = env('SHOPIFY_WEBHOOK_SECRET');

    Log::info('Webhook received for delete order', ['hmac' => $hmac, 'length' => strlen($body)]);

    if (!$secret) {
        return response('Webhook secret missing', 401);
    }

    $calculated = base64_encode(
        hash_hmac('sha256', $body, $secret, true)
    );

    if (!hash_equals($calculated, $hmac)) {
        return response('Invalid HMAC', 401);
    }

    $payload = json_decode($body, true);
    Log::info("Webhook payload for delete order", $payload);

    if (!empty($payload['id'])) {
        $order = Order::where('shopify_order_id', $payload['id'])->first();
        if ($order) {
            $order->delete();
            Log::info("Order deleted successfully", ['shopify_order_id' => $payload['id']]);
        }
    }

    return response('OK', 200);
}

    public function ordersCreate(Request $request)
    {
        return $this->handle($request);
    }

    public function ordersUpdate(Request $request)
    {
        return $this->handle($request);
    }

    public function ordersCancel(Request $request)
    {
        return $this->handle($request);
    }

    public function orderDelete(Request $request){

    return $this->deleteOrder($request);

    }
}
