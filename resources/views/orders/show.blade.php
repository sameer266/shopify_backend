@extends('layouts.app')

@section('title', 'Order ' . $order->order_number)

@section('content')
<!-- Header & Actions -->
<div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-3xl font-light text-black tracking-tight mb-2">Order {{ $order->order_number }}</h1>
        <p class="text-gray-500 text-sm">
            {{ $order->created_at->format('M j, Y \a\t g:i a') }} • 
            <span class="font-mono text-xs text-gray-400">ID: {{ $order->shopify_order_id }}</span>
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('orders.index') }}" class="border border-gray-300 px-4 py-2 text-sm text-gray-700 rounded hover:border-black transition bg-white">
            &larr; Back
        </a>
        
        @php
            // Cancel: Can only cancel if NOT (paid AND fulfilled) AND not already cancelled
            $canCancel = !($order->is_paid && $order->fulfillment_status === 'fulfilled') 
                        && is_null($order->cancelled_at);
            
            // Fulfill: Can only fulfill if not fully fulfilled
            $canFulfill = $order->fulfillment_status !== 'fulfilled';
            
            // Edit Qty: Can only edit if not fulfilled (Shopify doesn't allow editing fulfilled orders)
            $canEditQty = $order->fulfillment_status !== 'fulfilled' && is_null($order->cancelled_at);
            
            // Refund: Can only refund if order was paid
            $canRefund = $order->is_paid;
        @endphp

        @if($canFulfill)
        <button onclick="document.getElementById('fulfillModal').showModal()" class="bg-black text-white px-4 py-2 text-sm rounded hover:bg-gray-800 transition shadow-sm cursor-pointer">
            Fulfill Order
        </button>
        @endif

        @if($canEditQty)
        <button onclick="document.getElementById('editQtyModal').showModal()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 text-sm rounded hover:border-black transition shadow-sm cursor-pointer">
            Edit Qty
        </button>
        @endif

        @if($canCancel)
        <button onclick="document.getElementById('cancelOrderModal').showModal()" class="bg-red-50 border border-red-200 text-red-700 px-4 py-2 text-sm rounded hover:bg-red-100 transition shadow-sm cursor-pointer">
            Cancel Order
        </button>
        @endif
        
        @if($canRefund)
        <button onclick="document.getElementById('refundModal').showModal()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 text-sm rounded hover:border-black transition shadow-sm cursor-pointer">
            Refund
        </button>
        @endif
    </div>
</div>

<!-- Alerts -->
@if(session('success'))
<div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded text-sm relative" role="alert">
    <strong class="font-medium">Success!</strong> {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm relative" role="alert">
    <strong class="font-medium">Error!</strong> {{ session('error') }}
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Left Column: Order Details & Products -->
    <div class="lg:col-span-2 space-y-8">
        
        <!-- Products Table -->
        <div class="bg-white border border-gray-200 overflow-hidden rounded-lg shadow-sm">
            <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h3 class="text-sm uppercase tracking-wider text-gray-500 font-medium">Order Items</h3>
                <span class="text-xs text-gray-400">{{ $totalItems }} Items</span>
            </div>
            <div class="">
                <table id="orderItemsTable" class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 bg-white">
                            <th class="px-6 py-4 font-medium">Product</th>
                            <th class="px-6 py-4 font-medium">SKU</th>
                            <th class="px-6 py-4 font-medium text-right">Price</th>
                            <th class="px-6 py-4 font-medium text-right">Qty</th>
                            <th class="px-6 py-4 font-medium text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @foreach($order->orderItems as $item)
                        <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <span class="font-medium text-black block">{{ $item->title ?? 'Unknown Product' }}</span>
                                @if($item->variant_title)
                                <span class="text-gray-500 text-xs">{{ $item->variant_title }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-500 font-mono text-xs">{{ $item->sku ?? '—' }}</td>
                            <td class="px-6 py-4 text-right text-gray-700">{{ $order->currency }} {{ number_format($item->price, 2) }}</td>
                            <td class="px-6 py-4 text-right text-black font-medium">{{ $item->quantity }}</td>
                            <td class="px-6 py-4 text-right font-medium text-black">{{ $order->currency }} {{ number_format($item->price * $item->quantity, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment & Fulfillment Status -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white border border-gray-200 p-6 rounded-lg shadow-sm">
                <h3 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4">Payment Status</h3>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Status</span>
                    <x-status-badge type="payment" :value="$order->financial_status ?? ($order->is_paid ? 'paid' : 'pending')" />
                </div>
            </div>
            <div class="bg-white border border-gray-200 p-6 rounded-lg shadow-sm">
                <h3 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4">Fulfillment Status</h3>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Status</span>
                    <x-status-badge type="fulfillment" :value="$order->fulfillment_status ?? 'unfulfilled'" />
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="bg-white border border-gray-200 overflow-hidden rounded-lg shadow-sm">
             <div class="p-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-sm uppercase tracking-wider text-gray-500 font-medium">Payment History</h3>
            </div>
            <div class="">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 bg-white">
                            <th class="px-6 py-4 font-medium">Gateway</th>
                            <th class="px-6 py-4 font-medium">Amount</th>
                            <th class="px-6 py-4 font-medium">Status</th>
                            <th class="px-6 py-4 font-medium text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @forelse($order->payments as $payment)
                        <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 transition">
                            <td class="px-6 py-4 text-black">{{ $payment->gateway }}</td>
                            <td class="px-6 py-4 text-black">{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 capitalize border border-gray-200">
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
        <div class="bg-white border border-gray-200 overflow-hidden rounded-lg shadow-sm">
             <div class="p-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-sm uppercase tracking-wider text-gray-500 font-medium">Fulfillment History</h3>
            </div>
            <div class="">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 bg-white">
                            <th class="px-6 py-4 font-medium">Tracking Company</th>
                            <th class="px-6 py-4 font-medium">Tracking Number</th>
                            <th class="px-6 py-4 font-medium">Status</th>
                            <th class="px-6 py-4 font-medium text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @forelse($order->fulfillments as $fulfillment)
                        <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 transition">
                            <td class="px-6 py-4 text-black">{{ $fulfillment->tracking_company ?? '—' }}</td>
                            <td class="px-6 py-4 text-black font-mono text-xs">{{ $fulfillment->tracking_number ?? '—' }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 capitalize border border-gray-200">
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

        <!-- Refunds History -->
        <div class="bg-white border border-gray-200 overflow-hidden rounded-lg shadow-sm">
             <div class="p-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-sm uppercase tracking-wider text-gray-500 font-medium">Refunds History</h3>
            </div>
            <div class="">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 bg-white">
                            <th class="px-6 py-4 font-medium">Refund ID</th>
                            <th class="px-6 py-4 font-medium">Amount</th>
                            <th class="px-6 py-4 font-medium">Gateway</th>
                            <th class="px-6 py-4 font-medium text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @forelse($order->refunds as $refund)
                        <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 transition">
                            <td class="px-6 py-4 text-black font-mono text-xs">{{ Str::limit($refund->shopify_refund_id, 12, '..') }}</td>
                            <td class="px-6 py-4 text-red-600 font-medium">-{{ $order->currency }} {{ number_format($refund->total_amount, 2) }}</td>
                            <td class="px-6 py-4 text-gray-700">{{ $refund->gateway ?? '—' }}</td>
                            <td class="px-6 py-4 text-right text-gray-500">
                                {{ $refund->processed_at ? $refund->processed_at->format('M j, Y g:i a') : '—' }}
                            </td>
                        </tr>
                        @if($refund->refundItems->isNotEmpty())
                            <tr class="bg-gray-50">
                                <td colspan="4" class="px-6 py-3">
                                    <div class="text-xs text-gray-600 bg-white border border-gray-100 rounded p-3">
                                        <div class="font-medium text-gray-700 mb-2 border-b pb-1">Refunded Items</div>
                                        <ul class="space-y-2">
                                            @foreach($refund->refundItems as $item)
                                                <li class="flex justify-between">
                                                    <span>{{ $item->orderItem->title ?? 'Unknown' }} <span class="text-gray-400">× {{ $item->quantity }}</span></span>
                                                    <span class="text-red-600 font-mono">-Rs {{ number_format($item->subtotal, 2) }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @endif
                        @if($refund->orderAdjustments->isNotEmpty())
                            <tr class="bg-amber-50">
                                <td colspan="4" class="px-6 py-3">
                                    <div class="text-xs text-gray-600 bg-white border border-amber-100 rounded p-3">
                                        <div class="font-medium text-gray-700 mb-2 border-b pb-1">Order Adjustments</div>
                                        <ul class="space-y-2">
                                            @foreach($refund->orderAdjustments as $adj)
                                                <li class="flex justify-between items-center">
                                                    <span>
                                                        {{ ucfirst(str_replace('_', ' ', $adj->kind ?? 'refund_discrepancy')) }}
                                                        @if($adj->reason)<span class="text-gray-500">({{ $adj->reason }})</span>@endif
                                                    </span>
                                                    <span class="text-red-600 font-mono">-{{ $order->currency }} {{ number_format(abs($adj->amount), 2) }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @endif
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-sm text-gray-500 text-center italic">No refund records found.</td>
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
        <div class="bg-white border border-gray-200 p-6 rounded-lg shadow-sm">
            <h3 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4 border-b pb-2">Customer</h3>
            @if($order->customer)
                <div class="mb-4">
                    <p class="text-black font-medium text-lg">{{ $order->customer->first_name }} {{ $order->customer->last_name }}</p>
                    <p class="text-gray-600 text-sm hover:text-black transition mt-1">
                        <a href="mailto:{{ $order->customer->email }}" class="flex items-center gap-2">
                             <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                             {{ $order->customer->email }}
                        </a>
                    </p>
                    @if($order->customer->phone)
                        <p class="text-gray-600 text-sm mt-1 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            {{ $order->customer->phone }}
                        </p>
                    @endif
                </div>
            @else
                <p class="text-gray-500 italic text-sm">No customer linked</p>
            @endif
        </div>

        <!-- Shipping Address -->
        <div class="bg-white border border-gray-200 p-6 rounded-lg shadow-sm">
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
                        <p class="mt-2 text-gray-500 flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg> {{ $order->shipping_address['phone'] }}</p>
                    @endif
                </div>
            @else
                <p class="text-gray-500 text-sm italic">No shipping address provided</p>
            @endif
        </div>

        <!-- Order Summary -->
        <div class="bg-white border border-gray-200 p-6 rounded-lg shadow-sm">
            <h3 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4 border-b pb-2">Order Summary</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal</span>
                    <span>Rs {{ number_format($order->subtotal_price, 2) }}</span>
                </div>
                @if($order->total_discounts > 0)
                <div class="flex justify-between text-gray-600">
                    <span>Discounts</span>
                    <span class="text-green-600">-Rs {{ number_format($order->total_discounts, 2) }}</span>
                </div>
                @endif
                <div class="flex justify-between text-gray-600">
                    <span>Tax</span>
                    <span>Rs {{ number_format($order->total_tax, 2) }}</span>
                </div>
                
                <div class="border-t pt-2 mt-2 flex justify-between font-medium text-black text-base">
                    <span>Total</span>
                    <span>Rs {{ number_format($order->total_price, 2) }}</span>
                </div>
                @if($order->total_refunds > 0)
                <div class="flex justify-between text-red-600 font-medium pt-1">
                    <span>Refunded</span>
                    <span>-Rs {{ number_format($order->total_refunds, 2) }}</span>
                </div>
                <div class="border-t pt-2 mt-2 flex justify-between font-bold text-black text-base">
                    <span>Net Total</span>
                    <span>Rs {{ number_format($order->total_price - $order->total_refunds, 2) }}</span>
                </div>
                @endif
            </div>
        </div>

    </div>

</div>

<!-- MODALS -->

<!-- Fulfill Modal -->
<dialog id="fulfillModal" class="p-0 rounded-lg shadow-xl backdrop:bg-gray-800/50 w-full max-w-md m-auto">
    <div class="bg-white p-6 rounded-lg">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Fulfill Order</h3>
        <form action="{{ route('shopify.fulfill', $order->id) }}" method="POST">
            @csrf
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tracking Number (Optional)</label>
                <input type="text" name="tracking_number" class="w-full border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-black sm:text-sm">
            </div>

             <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tracking Company (Optional)</label>
                <input type="text" name="tracking_company" class="w-full border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-black sm:text-sm">
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('fulfillModal').close()" class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm text-white bg-black rounded-md hover:bg-gray-800 cursor-pointer flex items-center gap-2" onclick="this.innerHTML='<svg class=\'animate-spin h-4 w-4 text-white\' xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\'><circle class=\'opacity-25\' cx=\'12\' cy=\'12\' r=\'10\' stroke=\'currentColor\' stroke-width=\'4\'></circle><path class=\'opacity-75\' fill=\'currentColor\' d=\'M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z\'></path></svg> Fulfilling...'; this.disabled=true; this.form.submit();">
                    Fulfill Items
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- Cancel Order Modal -->
<dialog id="cancelOrderModal" class="p-0 rounded-lg shadow-xl backdrop:bg-gray-800/50 w-full max-w-md m-auto">
    <div class="bg-white p-6 rounded-lg">
        <h3 class="text-lg font-medium text-gray-900 mb-2">Cancel Order</h3>
        <p class="text-sm text-gray-500 mb-6">Are you sure you want to cancel this order? This action cannot be undone.</p>
        
        <form action="{{ route('shopify.cancel', $order->id) }}" method="POST">
            @csrf
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('cancelOrderModal').close()" class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer">Keep Order</button>
                <button type="submit" class="px-4 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700 cursor-pointer flex items-center gap-2" onclick="this.innerHTML='<svg class=\'animate-spin h-4 w-4 text-white\' xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\'><circle class=\'opacity-25\' cx=\'12\' cy=\'12\' r=\'10\' stroke=\'currentColor\' stroke-width=\'4\'></circle><path class=\'opacity-75\' fill=\'currentColor\' d=\'M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z\'></path></svg> Cancelling...'; this.disabled=true; this.form.submit();">
                    Yes, Cancel Order
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- Edit Quantity Modal -->
<dialog id="editQtyModal" class="p-0 rounded-lg shadow-xl backdrop:bg-gray-800/50 w-full max-w-md m-auto">
    <div class="bg-white p-6 rounded-lg">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Update Quantity</h3>
        <form action="{{ route('shopify.update-qty', $order->id) }}" method="POST">
            @csrf
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Line Item</label>
                <select name="line_item_id" id="editQtySelect" onchange="updateQtyInput()" required class="w-full border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-black sm:text-sm">
                    @foreach($order->orderItems as $item)
                        <option value="{{ $item->id }}" data-qty="{{ $item->quantity }}">{{ $item->title }} (Current: {{ $item->quantity }})</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">New Quantity</label>
                <input type="number" name="quantity" id="newQtyInput" min="0" required class="w-full border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-black sm:text-sm">
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('editQtyModal').close()" class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm text-white bg-black rounded-md hover:bg-gray-800 cursor-pointer flex items-center gap-2" onclick="this.innerHTML='<svg class=\'animate-spin h-4 w-4 text-white\' xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\'><circle class=\'opacity-25\' cx=\'12\' cy=\'12\' r=\'10\' stroke=\'currentColor\' stroke-width=\'4\'></circle><path class=\'opacity-75\' fill=\'currentColor\' d=\'M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z\'></path></svg> Updating...'; this.disabled=true; this.form.submit();">
                    Update
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- Refund Modal -->
<dialog id="refundModal" class="p-0 rounded-lg shadow-xl backdrop:bg-gray-800/50 w-full max-w-2xl m-auto">
    <div class="bg-white p-6 rounded-lg">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Create Refund</h3>
        <form action="{{ route('shopify.refund', $order->id) }}" method="POST">
            @csrf
            
             <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Restock Location</label>
                <select name="location_id" required class="w-full border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-black sm:text-sm">
                    @foreach($locations as $loc)
                        <option value="{{ $loc['id'] }}">{{ $loc['name'] }}</option>
                    @endforeach
                    @if(empty($locations))
                        <option value="" disabled selected>No locations found</option>
                    @endif
                </select>
                <p class="text-xs text-gray-500 mt-1">Select where items should be restocked.</p>
            </div>

            <div class="space-y-4 max-h-60 overflow-y-auto mb-6 pr-2">
                @foreach($order->orderItems as $index => $item)
                @if($item->quantity > 0)
                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                    <div class="flex items-center gap-3">
                         <input type="hidden" name="refund_items[{{ $index }}][id]" value="{{ $item->id }}">
                        <div class="text-sm">
                            <p class="font-medium text-gray-900">{{ $item->title }}</p>
                            <p class="text-gray-500 text-xs">SKU: {{ $item->sku }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-500">Refund Qty:</label>
                        <input type="number" name="refund_items[{{ $index }}][quantity]" value="0" min="0" max="{{ $item->quantity }}" class="w-20 border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-black sm:text-sm text-right">
                    </div>
                </div>
                @endif
                @endforeach
            </div>

            <div class="mb-6">
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="refund_shipping" value="1" class="rounded border-gray-300 text-black shadow-sm focus:border-black focus:ring-black">
                    <span class="text-sm text-gray-900">Refund Shipping Cost (Full)</span>
                </label>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('refundModal').close()" class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm text-white bg-black rounded-md hover:bg-gray-800 cursor-pointer flex items-center gap-2" onclick="this.innerHTML='<svg class=\'animate-spin h-4 w-4 text-white\' xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\'><circle class=\'opacity-25\' cx=\'12\' cy=\'12\' r=\'10\' stroke=\'currentColor\' stroke-width=\'4\'></circle><path class=\'opacity-75\' fill=\'currentColor\' d=\'M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z\'></path></svg> Creating Refund...'; this.disabled=true; this.form.submit();">
                    Create Refund
                </button>
            </div>
        </form>
    </div>
</dialog>


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
    
    dialog::backdrop {
        background: rgba(0, 0, 0, 0.4);
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

function updateQtyInput() {
    const select = document.getElementById('editQtySelect');
    const input = document.getElementById('newQtyInput');
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption) {
        input.value = selectedOption.getAttribute('data-qty');
    }
}
// Init immediately
updateQtyInput();
</script>
@endpush
