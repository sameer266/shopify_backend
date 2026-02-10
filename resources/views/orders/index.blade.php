@extends('layouts.app')

@section('title', 'Orders')

@section('content')
<div class="mb-8 flex justify-between items-center">
    <h1 class="text-3xl font-light text-black tracking-tight">Orders</h1>
    <form action="{{ route('sync.orders') }}" method="POST" x-data="{ loading: false }" @submit="loading = true">
        @csrf
        <button type="submit" :disabled="loading" :class="{ 'opacity-75 cursor-wait': loading }" class="bg-black text-white px-4 py-2 text-sm rounded hover:bg-gray-800 transition flex items-center gap-2">
            <span x-show="loading" class="inline-block animate-spin rounded-full h-3 w-3 border-b-2 border-white"></span>
            <span x-text="loading ? 'Syncing...' : 'Sync from Shopify'"></span>
        </button>
    </form>
</div>


@if(isset($summary))
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white border p-4 text-center rounded">
        <div class="text-gray-500 text-xs uppercase">Total Orders</div>
        <div class="text-black font-medium text-lg">{{ $summary['total_orders'] }}</div>
    </div>
    <div class="bg-white border p-4 text-center rounded">
        <div class="text-gray-500 text-xs uppercase">Paid Orders</div>
        <div class="text-black font-medium text-lg">{{ $summary['total_paid'] }}</div>
    </div>
    <div class="bg-white border p-4 text-center rounded">
        <div class="text-gray-500 text-xs uppercase">Unpaid Orders</div>
        <div class="text-black font-medium text-lg">{{ $summary['total_unpaid'] }}</div>
    </div>
    <div class="bg-white border p-4 text-center rounded">
        <div class="text-gray-500 text-xs uppercase">Total Revenue</div>
        <div class="text-black font-medium text-lg">Rs {{ number_format($summary['total_revenue'], 2) }}</div>
    </div>
</div>
@endif


<form method="get" action="{{ route('orders.index') }}" class="bg-white border border-gray-200 p-6 mb-8 rounded-lg shadow-sm">
    <!-- Filters Title -->
    <div class="mb-6">
        <span class="text-xs uppercase tracking-wider text-gray-500 font-medium">Filters</span>
    </div>

    <!-- Main Filters Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Search Field -->
        <div class="lg:col-span-2">
            <label class="block text-xs uppercase tracking-wider text-gray-500 mb-2">Search</label>
            <input 
                type="text" 
                name="search" 
                value="{{ request('search') }}" 
                placeholder="Order ID or customer..." 
                class="w-full border border-gray-400 text-sm rounded px-3 py-2 focus:outline-none focus:border-black focus:ring-1 focus:ring-black"
            >
        </div>

        <!-- Payment Status -->
        <div>
            <label class="block text-xs uppercase tracking-wider text-gray-500 mb-2">Payment</label>
            <select 
                name="payment_status" 
                class="w-full border border-gray-400 text-sm rounded px-3 py-2 focus:outline-none focus:border-black focus:ring-1 focus:ring-black"
            >
                <option value="">All</option>
                <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="unpaid" {{ request('payment_status') === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
            </select>
        </div>

        <!-- Fulfillment Status -->
        <div>
            <label class="block text-xs uppercase tracking-wider text-gray-500 mb-2">Fulfillment</label>
            <select 
                name="fulfillment_status" 
                class="w-full border border-gray-400 text-sm rounded px-3 py-2 focus:outline-none focus:border-black focus:ring-1 focus:ring-black"
            >
                <option value="">All</option>
                <option value="fulfilled" {{ request('fulfillment_status') === 'fulfilled' ? 'selected' : '' }}>Fulfilled</option>
                <option value="partial" {{ request('fulfillment_status') === 'partial' ? 'selected' : '' }}>Partial</option>
                <option value="unfulfilled" {{ request('fulfillment_status') === 'unfulfilled' ? 'selected' : '' }}>Unfulfilled</option>
            </select>
        </div>

        <!-- Shipping Status -->
        <div>
            <label class="block text-xs uppercase tracking-wider text-gray-500 mb-2">Shipping</label>
            <select 
                name="shipping_status" 
                class="w-full border border-gray-400 text-sm rounded px-3 py-2 focus:outline-none focus:border-black focus:ring-1 focus:ring-black"
            >
                <option value="">All</option>
                <option value="fulfilled" {{ request('shipping_status') === 'fulfilled' ? 'selected' : '' }}>Fulfilled</option>
                <option value="unfulfilled" {{ request('shipping_status') === 'unfulfilled' ? 'selected' : '' }}>Unfulfilled</option>
            </select>
        </div>
    </div>

    <!-- Date Filters & Buttons -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
        <!-- Date From -->
        <div>
            <label class="block text-xs uppercase tracking-wider text-gray-500 mb-2">Date from</label>
            <input 
                type="date" 
                name="date_from" 
                value="{{ request('date_from') }}" 
                class="w-full border border-gray-400 text-sm rounded px-3 py-2 focus:outline-none focus:border-black focus:ring-1 focus:ring-black"
            >
        </div>

        <!-- Date To -->
        <div>
            <label class="block text-xs uppercase tracking-wider text-gray-500 mb-2">Date to</label>
            <input 
                type="date" 
                name="date_to" 
                value="{{ request('date_to') }}" 
                class="w-full border border-gray-400 text-sm rounded px-3 py-2 focus:outline-none focus:border-black focus:ring-1 focus:ring-black"
            >
        </div>

        <!-- Buttons -->
        <div class="flex items-end gap-2">
            <button 
                type="submit" 
                class="border border-black bg-black text-white px-6 py-2 text-sm rounded hover:bg-gray-800 transition"
            >
                Filter
            </button>
            <a 
                href="{{ route('orders.index') }}" 
                class="border border-gray-400 px-6 py-2 text-sm text-gray-700 rounded hover:border-black transition"
            >
                Reset
            </a>
        </div>
    </div>
</form>




<div class="bg-white border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table id="ordersTable" class="min-w-full divide-y divide-gray-200 display" style="width:100%">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-gray-500 font-medium">Order ID</th>
                    <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-gray-500 font-medium">Customer</th>
                    <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-gray-500 font-medium">Payment</th>
                    <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-gray-500 font-medium">Fulfillment</th>
                    <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-gray-500 font-medium">Shipping</th>
                    <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-gray-500 font-medium">Total Items</th>
                    <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-gray-500 font-medium">Total</th>
                    <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-gray-500 font-medium">Date</th>
                    <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-gray-500 font-medium">Action</th>

                    
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($orders as $order)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 text-sm font-medium text-black">{{ $order->order_number ?: $order->shopify_order_id }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700">{{ $order->customer_name }}</td>
                    <td class="px-6 py-4"><x-status-badge type="payment" :value="$order->financial_status ?? ($order->is_paid ? 'paid' : 'pending')" /></td>
                    <td class="px-6 py-4"><x-status-badge type="fulfillment" :value="$order->fulfillment_status ?? 'unfulfilled'" /></td>
                    <td class="px-6 py-4"><x-status-badge type="fulfillment" :value="$order->shipping_status ?? '—'" /></td>
                   <td class="px-6 py-4 text-sm text-gray-600">{{ $order->orderItems->sum('quantity') }}</td>

                    <td class="px-6 py-4 text-sm text-right text-black">{{ number_format($order->total_price, 2) }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $order->created_at->format('M j, Y') }}</td>
                   <td class="px-6 py-4 text-sm text-center">
    <a href="{{ route('orders.show', $order) }}" class="text-black hover:text-gray-600 transition">
        <i class="fa fa-eye"></i>
    </a>
</td>

                </tr>
                @empty
                <tr><td colspan="7" class="px-6 py-16 text-center text-gray-400 text-sm">No orders. Sync from Shopify.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('styles')

<style>
    /* DataTables minimal black and white styling */
    .dataTables_wrapper .dataTables_length select,
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #d1d5db;
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
    .dataTables_wrapper .dataTables_length select:focus,
    .dataTables_wrapper .dataTables_filter input:focus {
        outline: none;
        border-color: #000;
        ring: 1px solid #000;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #000 !important;
        color: #fff !important;
        border: 1px solid #000 !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #f9fafb !important;
        color: #000 !important;
        border: 1px solid #d1d5db !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        color: #374151 !important;
        border: 1px solid #e5e7eb !important;
    }
    .dataTables_wrapper .dataTables_info {
        color: #6b7280;
        font-size: 0.875rem;
    }
</style>
@endpush

@push('scripts')

<script>
$(function() {
    var t = $('#ordersTable');
    if (t.find('tbody tr').length && !t.find('tbody tr td[colspan]').length) {
        t.DataTable({ order: [[6, 'desc']], pageLength: 25 });
    }
});
</script>
@endpush