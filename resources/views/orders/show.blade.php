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
            <a href="{{ route('orders.index') }}"
                class="border border-gray-300 px-4 py-2 text-sm text-gray-700 rounded hover:border-black transition bg-white">
                &larr; Back
            </a>

            @php
                $isCancelled = !is_null($order->cancelled_at);
                $isPaid = in_array($order->financial_status, [
                    'paid',
                    'partially_paid',
                    'partially_refunded',
                    'refunded',
                ]);
                $isPartiallyRefunded = $order->financial_status === 'partially_refunded';
                $isFulfilled = $order->fulfillment_status === 'fulfilled';

                // Button Conditions (Following Shopify rules)
                $canFulfill = !$isFulfilled && !$isCancelled;
                $canEditQty = !$isFulfilled && !$isCancelled;
                $canCancel = !$isCancelled;
                $canRefund = !$isCancelled && ($isPaid || $isPartiallyRefunded);
            @endphp

            {{-- Fulfill --}}
            @if ($canFulfill)
                <button onclick="document.getElementById('fulfillModal').showModal()"
                    class="bg-black text-white px-4 py-2 text-sm rounded hover:bg-gray-800 transition shadow-sm">
                    Fulfill Order
                </button>
            @endif

            {{-- Edit Quantity --}}
            @if ($canEditQty)
                <button onclick="document.getElementById('editQtyModal').showModal()"
                    class="bg-white border border-gray-300 text-gray-700 px-4 py-2 text-sm rounded hover:border-black transition shadow-sm">
                    Edit Qty
                </button>
            @endif

            {{-- Cancel Order --}}
            @if ($canCancel)
                <button onclick="document.getElementById('cancelOrderModal').showModal()"
                    class="bg-white border border-gray-300 text-gray-700 px-4 py-2 text-sm rounded hover:border-black transition shadow-sm">
                    Cancel Order
                </button>
            @endif

            {{-- Refund --}}
            @if ($canRefund)
                <button onclick="document.getElementById('refundModal').showModal()"
                    class="bg-white border border-gray-300 text-gray-700 px-4 py-2 text-sm rounded hover:border-black transition shadow-sm">
                    Refund
                </button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Order Details & Products -->
        <div class="lg:col-span-2 space-y-8">

            <!-- Products Table with DataTables -->
            <div class="bg-white overflow-hidden rounded-lg shadow-sm border border-gray-200">
                <div class="p-4 bg-gray-50 flex justify-between items-center border-b border-gray-200">
                    <h3 class="text-sm font-medium uppercase text-gray-700">Order Items</h3>
                    <span class="text-xs text-gray-500">{{ $totalItems }} Items</span>
                </div>

                <div class="overflow-x-auto p-4">
                    <table id="orderItemsTable" class="w-full text-left text-sm display" style="width:100%">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr class="text-xs uppercase text-gray-500">
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3">SKU</th>
                                <th class="px-4 py-3 text-right">Price</th>
                                <th class="px-4 py-3 text-right">Qty</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Discount</th>
                                <th class="px-4 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->orderItems as $item)
                                @php
                                    $refundedQty = $order->refunds
                                        ->flatMap(fn($r) => $r->refundItems)
                                        ->where('order_item_id', $item->id)
                                        ->sum('quantity');

                                    $restockedQty = $order->refunds
                                        ->flatMap(fn($r) => $r->refundItems)
                                        ->where('order_item_id', $item->id)
                                        ->where('restock_type', 'return')
                                        ->sum('quantity');

                                  // Get the order item's fulfillment status
                                    $fulfilledStatus = $item->fulfillment_status ?? 'unfulfilled'; // values: 'fulfilled', 'partial', 'unfulfilled'

                                    // Determine item status
                                    if ($fulfilledStatus === 'restocked') {
                                        $status = 'restocked';
                                    } elseif ($fulfilledStatus === 'refunded') {
                                        $status = 'refunded';
                                    } elseif ($fulfilledStatus === 'partially_refunded') {
                                        $status = 'partially_refunded';
                                    } elseif ($fulfilledStatus === 'fulfilled') {
                                        $status = 'fulfilled';
                                    } elseif ($fulfilledStatus === 'partial') {
                                        $status = 'partially_fulfilled';
                                    } else {
                                        $status = 'unfulfilled';
                                    }

                                    // Badge classes for table display
                                    $badgeClass = match ($status) {
                                        'restocked' => 'bg-blue-100 text-blue-800',
                                        'refunded' => 'bg-red-100 text-red-800',
                                        'partially_refunded' => 'bg-gray-100 text-gray-800',
                                        'fulfilled' => 'bg-green-100 text-green-800',
                                        'partially_fulfilled' => 'bg-yellow-100 text-yellow-800',
                                        default => 'bg-gray-50 text-gray-600',
                                                                        };

                                @endphp

                                <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $item->title ?? 'Unknown Product' }}</div>
                                        @if ($item->variant_title)
                                            <div class="text-xs text-gray-500">{{ $item->variant_title }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $item->sku ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900">Rs {{ number_format($item->price, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-900">
                                        {{ $item->quantity - $refundedQty }}
                                        @if ($refundedQty > 0)
                                            <span class="text-red-600 text-xs ml-1">({{ $refundedQty }} ref)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-900 font-medium">
                                        Rs {{ number_format(($item->quantity - $refundedQty) * $item->price, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-green-600">
                                        Rs {{ number_format($item->discount_allocation, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </span>
                                    </td>
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
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="text-xs uppercase text-gray-500 border-b border-gray-200 bg-white">
                            <tr>
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
                                    <td class="px-6 py-4 text-black font-medium">Rs
                                        {{ number_format($payment->amount, 2) }}</td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            {{ $payment->status === 'success' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ ucfirst($payment->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-500">
                                        {{ $payment->processed_at ? $payment->processed_at->format('M j, Y g:i a') : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-sm text-gray-500 text-center italic">No payment
                                        records found.</td>
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
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="text-xs uppercase text-gray-500 border-b border-gray-200 bg-white">
                            <tr>
                                <th class="px-6 py-3 font-medium">Tracking Company</th>
                                <th class="px-6 py-3 font-medium">Tracking Number</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            @forelse($order->fulfillments as $fulfillment)
                                <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                                    <td class="px-6 py-3 text-black">{{ $fulfillment->tracking_company ?? '—' }}</td>
                                    <td class="px-6 py-3 text-black font-mono text-xs">
                                        {{ $fulfillment->tracking_number ?? '—' }}</td>
                                    <td class="px-6 py-3">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            {{ $fulfillment->status === 'success' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ ucfirst($fulfillment->status ?? 'pending') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-right text-gray-500">
                                        {{ $fulfillment->created_at ? $fulfillment->created_at->format('M j, Y g:i a') : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-sm text-gray-500 text-center italic">
                                        No fulfillment records found.
                                    </td>
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
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="text-xs uppercase text-gray-500 border-b border-gray-200 bg-white">
                            <tr>
                                <th class="px-6 py-3 font-medium">Refund ID</th>
                                <th class="px-6 py-3 font-medium">Amount</th>
                                <th class="px-6 py-3 font-medium">Gateway</th>
                                <th class="px-6 py-3 font-medium">Date</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            @forelse($order->refunds as $refund)
                                <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                                    <td class="px-6 py-3 font-mono text-xs">
                                        {{ Str::limit($refund->shopify_refund_id, 12, '..') }}</td>
                                    <td class="px-6 py-3 text-red-600 font-medium">-Rs
                                        {{ number_format($refund->total_amount, 2) }}</td>
                                    <td class="px-6 py-3">{{ $refund->gateway ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-500">
                                        {{ $refund->processed_at?->format('M j, Y g:i a') ?? '—' }}</td>
                                </tr>

                                @if ($refund->refundItems->isNotEmpty())
                                    <tr class="bg-gray-50">
                                        <td colspan="4" class="px-6 py-2">
                                            <div class="text-xs text-gray-700 border p-2 rounded">
                                                <div class="font-semibold border-b mb-1 pb-1">Refunded Items</div>
                                                <ul class="space-y-1">
                                                    @foreach ($refund->refundItems as $item)
                                                        <li class="flex justify-between">
                                                            <span>{{ $item->orderItem->title ?? 'Unknown' }} <small
                                                                    class="text-gray-400">×
                                                                    {{ $item->quantity }}</small></span>
                                                            <span class="text-red-600 font-mono">Rs
                                                                {{ number_format($item->subtotal, 2) }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @endif

                                @if ($refund->orderAdjustments->isNotEmpty())
                                    <tr class="bg-gray-50">
                                        <td colspan="4" class="px-6 py-2">
                                            <div class="text-xs text-gray-700 border p-2 rounded">
                                                <div class="font-semibold border-b mb-1 pb-1">Order Adjustments</div>
                                                <ul class="space-y-1">
                                                    @foreach ($refund->orderAdjustments as $adj)
                                                        <li class="flex justify-between items-center">
                                                            <span>{{ ucfirst(str_replace('_', ' ', $adj->kind ?? 'refund_discrepancy')) }}</span>
                                                            <span class="text-red-600 font-mono">-Rs
                                                                {{ number_format(abs($adj->amount), 2) }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500 italic">No refund
                                        records found.</td>
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
                @if ($order->customer)
                    <div class="mb-4">
                        <p class="text-black font-medium text-lg">{{ $order->customer->first_name }}
                            {{ $order->customer->last_name }}</p>
                        <p class="text-gray-600 text-sm hover:text-black transition mt-1">
                            <a href="mailto:{{ $order->customer->email }}" class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                    </path>
                                </svg>
                                {{ $order->customer->email }}
                            </a>
                        </p>
                        @if ($order->customer->phone)
                            <p class="text-gray-600 text-sm mt-1 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                    </path>
                                </svg>
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
                <h3 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4 border-b pb-2">Shipping Address
                </h3>
                @if ($order->shipping_address)
                    <div class="text-sm text-gray-700 leading-relaxed">
                        <p class="font-medium text-black">{{ $order->shipping_address['name'] ?? '' }}</p>
                        <p>{{ $order->shipping_address['address1'] ?? '' }}</p>
                        @if (!empty($order->shipping_address['address2']))
                            <p>{{ $order->shipping_address['address2'] }}</p>
                        @endif
                        <p>
                            {{ $order->shipping_address['city'] ?? '' }}
                            {{ isset($order->shipping_address['province_code']) ? ', ' . $order->shipping_address['province_code'] : '' }}
                            {{ $order->shipping_address['zip'] ?? '' }}
                        </p>
                        <p>{{ $order->shipping_address['country'] ?? '' }}</p>
                        @if (!empty($order->shipping_address['phone']))
                            <p class="mt-2 text-gray-500 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                    </path>
                                </svg>
                                {{ $order->shipping_address['phone'] }}
                            </p>
                        @endif
                    </div>
                @else
                    <p class="text-gray-500 text-sm italic">No shipping address provided</p>
                @endif
            </div>

            <!-- Order Summary -->
            <div class="bg-white border border-gray-200 p-6 rounded-lg shadow-sm">
                <h3 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4 border-b pb-2">Order Summary
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span>Rs {{ number_format($order->subtotal_price, 2) }}</span>
                    </div>
                    @if ($order->total_discounts > 0)
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
                    @if ($order->total_refunds > 0)
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
            <form action="{{ route('shopify.fulfill', $order->id) }}" method="POST" id="fulfillForm">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tracking Number (Optional)</label>
                    <input type="text" name="tracking_number"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-black sm:text-sm">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tracking Company (Optional)</label>
                    <input type="text" name="tracking_company"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-black sm:text-sm">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('fulfillModal').close()"
                        class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="fulfillBtn"
                        class="px-4 py-2 text-sm text-white bg-black rounded-md hover:bg-gray-800 flex items-center gap-2">
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
            <p class="text-sm text-gray-500 mb-6">Are you sure you want to cancel this order? This action cannot be undone.
            </p>
            <form action="{{ route('shopify.cancel', $order->id) }}" method="POST" id="cancelForm">
                @csrf
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('cancelOrderModal').close()"
                        class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Keep
                        Order</button>
                    <button type="submit" id="cancelBtn"
                        class="px-4 py-2 text-sm text-white bg-gray-700 rounded-md hover:bg-gray-800 flex items-center gap-2">
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
            <form action="{{ route('shopify.update-qty', $order->id) }}" method="POST" id="editQtyForm">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Line Item</label>
                    <select name="line_item_id" id="editQtySelect" onchange="updateQtyInput()" required
                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-black sm:text-sm">
                        @foreach ($order->orderItems as $item)
                            <option value="{{ $item->id }}" data-qty="{{ $item->quantity }}">{{ $item->title }}
                                (Current: {{ $item->quantity }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Quantity</label>
                    <input type="number" name="quantity" id="newQtyInput" min="0" required
                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-black sm:text-sm">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('editQtyModal').close()"
                        class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="editQtyBtn"
                        class="px-4 py-2 text-sm text-white bg-black rounded-md hover:bg-gray-800 flex items-center gap-2">
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
            <form action="{{ route('shopify.refund', $order->id) }}" method="POST" id="refundForm">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Restock Location</label>
                    <select name="location_id" required
                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-black sm:text-sm">
                        @foreach ($locations as $loc)
                            <option value="{{ $loc['id'] }}">{{ $loc['name'] }}</option>
                        @endforeach
                        @if (empty($locations))
                            <option value="" disabled selected>No locations found</option>
                        @endif
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Select where items should be restocked.</p>
                </div>
                <div class="space-y-4 max-h-60 overflow-y-auto mb-6 pr-2">
                    @foreach ($order->orderItems as $index => $item)
                        @if ($item->quantity > 0)
                            <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                                <div class="flex items-center gap-3">
                                    <input type="hidden" name="refund_items[{{ $index }}][id]"
                                        value="{{ $item->id }}">
                                    <div class="text-sm">
                                        <p class="font-medium text-gray-900">{{ $item->title }}</p>
                                        <p class="text-gray-500 text-xs">SKU: {{ $item->sku }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-gray-500">Refund Qty:</label>
                                    <input type="number" name="refund_items[{{ $index }}][quantity]"
                                        value="0" min="0" max="{{ $item->quantity }}"
                                        class="w-20 border-gray-300 rounded-md shadow-sm focus:border-black focus:ring-black sm:text-sm text-right">
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('refundModal').close()"
                        class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="refundBtn"
                        class="px-4 py-2 text-sm text-white bg-black rounded-md hover:bg-gray-800 flex items-center gap-2">
                        Create Refund
                    </button>
                </div>
            </form>
        </div>
    </dialog>

@endsection

@push('styles')
    <style>
        dialog::backdrop {
            background: rgba(0, 0, 0, 0.4);
        }

        /* DataTables minimal styling */
        #orderItemsTable_wrapper .dataTables_length,
        #orderItemsTable_wrapper .dataTables_filter,
        #orderItemsTable_wrapper .dataTables_info,
        #orderItemsTable_wrapper .dataTables_paginate {
            padding: 0.5rem 0;
        }

        #orderItemsTable_wrapper .dataTables_length select,
        #orderItemsTable_wrapper .dataTables_filter input {
            border: 1px solid #d1d5db;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.25rem;
        }

        #orderItemsTable_wrapper .dataTables_paginate .paginate_button.current {
            background: #000 !important;
            color: #fff !important;
            border: 1px solid #000 !important;
            border-radius: 0.25rem;
        }

        #orderItemsTable_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f3f4f6 !important;
            color: #000 !important;
            border: 1px solid #d1d5db !important;
        }

        #orderItemsTable_wrapper .dataTables_paginate .paginate_button {
            color: #374151 !important;
            border: 1px solid #e5e7eb !important;
            margin: 0 2px;
            border-radius: 0.25rem;
        }
    </style>
@endpush

@push('scripts')
    <script>
        function updateQtyInput() {
            const select = document.getElementById('editQtySelect');
            const input = document.getElementById('newQtyInput');
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption) {
                input.value = selectedOption.getAttribute('data-qty');
            }
        }
        updateQtyInput();

        // Initialize DataTables for order items
        $(document).ready(function() {
            var table = $('#orderItemsTable');
            if (table.find('tbody tr').length > 0) {
                table.DataTable({
                    order: [
                        [0, 'asc']
                    ],
                    pageLength: 10,
                    dom: 'frtip',
                    language: {
                        search: "",
                        searchPlaceholder: "Search items..."
                    }
                });
            }
        });

        // Prevent duplicate submissions and show loading state
        const forms = ['fulfillForm', 'cancelForm', 'editQtyForm', 'refundForm'];
        const buttons = ['fulfillBtn', 'cancelBtn', 'editQtyBtn', 'refundBtn'];

        forms.forEach((formId, index) => {
            const form = document.getElementById(formId);
            const btn = document.getElementById(buttons[index]);

            if (form && btn) {
                form.addEventListener('submit', function(e) {
                    btn.innerHTML =
                        '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...';
                    btn.disabled = true;
                });
            }
        });
    </script>
@endpush
