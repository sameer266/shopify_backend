@extends('layouts.app')

@section('title', 'Order ' . $order->order_number)

@section('content')
<div class="mb-8 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-light text-black tracking-tight mb-2">Order {{ $order->order_number }}</h1>
        <p class="text-gray-500 text-sm">{{ $order->created_at->format('M j, Y \a\t g:i a') }}</p>
    </div>
    <a href="{{ route('orders.index') }}" class="border border-gray-400 px-4 py-2 text-sm text-gray-700 rounded hover:border-black transition">
        &larr; Back to Orders
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Left Column: Order Details & Products -->
    <div class="lg:col-span-2 space-y-8">
        
        <!-- Products Table -->
        <div class="bg-white border border-gray-200 overflow-hidden rounded-lg">
            <div class="p-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-sm uppercase tracking-wider text-gray-500 font-medium">Order Items</h3>
            </div>
            <div class="p-0">
                <table id="orderItemsTable" class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-gray-500 border-b border-gray-200">
                            <th class="px-6 py-4 font-medium">Product</th>
                            <th class="px-6 py-4 font-medium">SKU</th>
                            <th class="px-6 py-4 font-medium text-right">Price</th>
                            <th class="px-6 py-4 font-medium text-right">Qty</th>
                            <th class="px-6 py-4 font-medium text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @foreach($order->orderItems as $item)
                        <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <span class="font-medium text-black block">{{ $item->product?->title ?? 'Unknown Product' }}</span>
                                <span class="text-gray-500 text-xs">{{ $item->title }}</span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">{{ $item->sku ?? '—' }}</td>
                            <td class="px-6 py-4 text-right text-gray-700">Rs {{ number_format($item->price, 2) }}</td>
                            <td class="px-6 py-4 text-right text-black">{{ $item->quantity }}</td>
                            <td class="px-6 py-4 text-right font-medium text-black">Rs {{ number_format($item->price * $item->quantity, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment & Fulfillment Status -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white border border-gray-200 p-6 rounded-lg">
                <h3 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4">Payment Status</h3>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Status</span>
                    <x-status-badge type="payment" :value="$order->financial_status ?? ($order->is_paid ? 'paid' : 'pending')" />
                </div>
            </div>
            <div class="bg-white border border-gray-200 p-6 rounded-lg">
                <h3 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4">Fulfillment Status</h3>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Status</span>
                    <x-status-badge type="fulfillment" :value="$order->fulfillment_status ?? 'unfulfilled'" />
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="bg-white border border-gray-200 overflow-hidden rounded-lg">
             <div class="p-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-sm uppercase tracking-wider text-gray-500 font-medium">Payment History</h3>
            </div>
            <div class="p-0">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-gray-500 border-b border-gray-200">
                            <th class="px-6 py-4 font-medium">Gateway</th>
                            <th class="px-6 py-4 font-medium">Amount</th>
                            <th class="px-6 py-4 font-medium">Status</th>
                            <th class="px-6 py-4 font-medium text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @forelse($order->payments as $payment)
                        <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50">
                            <td class="px-6 py-4 text-black">{{ $payment->gateway }}</td>
                            <td class="px-6 py-4 text-black">{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 capitalize">
                                    {{ $payment->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-gray-500">
                                {{ $payment->processed_at ? $payment->processed_at->format('M j, Y g:i a') : '—' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-sm text-gray-500 text-center italic">No payment records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Fulfillment History -->
        <div class="bg-white border border-gray-200 overflow-hidden rounded-lg">
             <div class="p-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-sm uppercase tracking-wider text-gray-500 font-medium">Fulfillment History</h3>
            </div>
            <div class="p-0">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-gray-500 border-b border-gray-200">
                            <th class="px-6 py-4 font-medium">Tracking Company</th>
                            <th class="px-6 py-4 font-medium">Tracking Number</th>
                            <th class="px-6 py-4 font-medium">Status</th>
                            <th class="px-6 py-4 font-medium text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @forelse($order->fulfillments as $fulfillment)
                        <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50">
                            <td class="px-6 py-4 text-black">{{ $fulfillment->tracking_company ?? '—' }}</td>
                            <td class="px-6 py-4 text-black font-mono text-xs">{{ $fulfillment->tracking_number ?? '—' }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 capitalize">
                                    {{ $fulfillment->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-gray-500">
                                {{ $fulfillment->created_at_shopify ? $fulfillment->created_at_shopify->format('M j, Y g:i a') : '—' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-sm text-gray-500 text-center italic">No fulfillment records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Customer & Summary -->
<div class="space-y-6">

    <!-- Customer Details -->
    <div class="bg-white border border-gray-200 p-6 rounded-lg">
        <h3 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4 border-b pb-2">Customer</h3>
        @if($order->customer)
            <div class="mb-4">
                <p class="text-black font-medium">{{ $order->customer->first_name }} {{ $order->customer->last_name }}</p>
                <p class="text-gray-600 text-sm hover:text-black transition">
                    <a href="mailto:{{ $order->customer->email }}">{{ $order->customer->email }}</a>
                </p>
                @if($order->customer->phone)
                    <p class="text-gray-600 text-sm">{{ $order->customer->phone }}</p>
                @endif
            </div>
        @else
            <p class="text-gray-500 italic text-sm">No customer linked</p>
        @endif
    </div>

    <!-- Shipping Address -->
    <div class="bg-white border border-gray-200 p-6 rounded-lg">
        <h3 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4 border-b pb-2">Shipping Address</h3>
        @if($order->shipping_address )
            <div class="text-sm text-gray-700 leading-relaxed">
                <p class="font-medium text-black">{{ $order->shipping_address['name'] ?? '' }}</p>
                <p>{{ $order->shipping_address['address1'] ?? '' }}</p>
                @if(!empty($order->shipping_address['address2']))
                    <p>{{ $order->shipping_address['address2'] }}</p>
                @endif
                <p>
                    {{ $order->shipping_address['city'] ?? '' }}
                    {{ isset($order->shipping_address['province_code']) ? ', ' . $order->shipping_address['province_code'] : '' }}
                    {{ $order->shipping_address['zip'] ?? '' }}
                </p>
                <p>{{ $order->shipping_address['country'] ?? '' }}</p>
                @if(!empty($order->shipping_address['phone']))
                    <p class="mt-2 text-gray-500">{{ $order->shipping_address['phone'] }}</p>
                @endif
            </div>
        @else
            <p class="text-gray-500 text-sm italic">No shipping address provided</p>
        @endif
    </div>

    <!-- Billing Address -->
    <div class="bg-white border border-gray-200 p-6 rounded-lg">
        <h3 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4 border-b pb-2">Billing Address</h3>
        @if($order->billing_address && is_array($order->billing_address))
            <div class="text-sm text-gray-700 leading-relaxed">
                <p class="font-medium text-black">{{ $order->billing_address['name'] ?? '' }}</p>
                <p>{{ $order->billing_address['address1'] ?? '' }}</p>
                @if(!empty($order->billing_address['address2']))
                    <p>{{ $order->billing_address['address2'] }}</p>
                @endif
                <p>
                    {{ $order->billing_address['city'] ?? '' }}
                    {{ isset($order->billing_address['province_code']) ? ', ' . $order->billing_address['province_code'] : '' }}
                    {{ $order->billing_address['zip'] ?? '' }}
                </p>
                <p>{{ $order->billing_address['country'] ?? '' }}</p>
                @if(!empty($order->billing_address['phone']))
                    <p class="mt-2 text-gray-500">{{ $order->billing_address['phone'] }}</p>
                @endif
            </div>
        @else
            <p class="text-gray-500 text-sm italic">No billing address provided</p>
        @endif
    </div>

    <!-- Order Summary -->
    <div class="bg-white border border-gray-200 p-6 rounded-lg">
        <h3 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4 border-b pb-2">Order Summary</h3>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between text-gray-600">
                <span>Subtotal</span>
                <span>Rs {{ number_format($order->subtotal_price, 2) }}</span>
            </div>
            <div class="flex justify-between text-gray-600">
                <span>Tax</span>
                <span>Rs {{ number_format($order->total_tax, 2) }}</span>
            </div>
            <div class="flex justify-between text-gray-600">
                <span>Shipping</span>
                <span>—</span> <!-- Add shipping cost if available -->
            </div>
            <div class="border-t pt-2 mt-2 flex justify-between font-medium text-black text-base">
                <span>Total</span>
                <span>Rs {{ number_format($order->total_price, 2) }}</span>
            </div>
        </div>
    </div>

</div>

</div>
@endsection

@push('styles')
<style>
    /* Minimalist Styles for Details Table */
    #orderItemsTable_wrapper .dataTables_filter input {
        border: 1px solid #e5e7eb;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 14px;
    }
    #orderItemsTable_wrapper .dataTables_filter input:focus {
        border-color: #000;
        outline: none;
    }
    #orderItemsTable_wrapper .dataTables_length select {
        border: 1px solid #e5e7eb;
        border-radius: 4px;
        padding: 4px 24px 4px 8px;
        font-size: 14px;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #000 !important;
        color: #fff !important;
        border: 1px solid #000 !important;
        border-radius: 4px;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #f3f4f6 !important;
        color: #000 !important;
        border: 1px solid #d1d5db !important;
    }
</style>
@endpush

@push('scripts')
<script>
$(function() {
    $('#orderItemsTable').DataTable({
        paging: false,
        searching: false,
        info: false,
        order: [], // Disable initial sort
        columnDefs: [
            { orderable: false, targets: [0, 4] } // Disable sorting on Product & Total cols if desired
        ]
    });
});
</script>
@endpush
