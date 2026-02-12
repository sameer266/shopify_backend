@extends('layouts.app')

@section('title', 'Reports & Analysis')

@section('content')
<div x-data="{ range: '{{ $range }}', updateRange(e) { this.range = e.target.value; } }" class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <h1 class="text-3xl font-light text-black tracking-tight">
        Reports & Analysis
    </h1>
    
    <form method="get" action="{{ route('reports.index') }}" class="flex flex-wrap items-center gap-2">
        <!-- Range Select -->
        <select 
            name="range" 
            class="rounded border border-gray-400 text-sm px-2 py-1 focus:outline-none focus:border-black focus:ring-1 focus:ring-black"
            @change="if($event.target.value !== 'custom') { $event.target.form.submit() } else { updateRange($event) }"
        >
            <option value="today" {{ $range === 'today' ? 'selected' : '' }}>Today</option>
            <option value="7d" {{ $range === '7d' ? 'selected' : '' }}>Last 7 days</option>
            <option value="30d" {{ $range === '30d' ? 'selected' : '' }}>Last 30 days</option>
            <option value="custom" {{ $range === 'custom' ? 'selected' : '' }}>Custom</option>
        </select>

        <!-- Custom Date Range -->
        <span x-show="range === 'custom'" x-cloak class="flex items-center gap-1">
            <input 
                type="date" 
                name="start" 
                value="{{ $dateStart }}" 
                class="rounded border border-gray-400 text-sm w-36 px-2 py-1 focus:outline-none focus:border-black focus:ring-1 focus:ring-black"
            >
            <input 
                type="date" 
                name="end" 
                value="{{ $dateEnd }}" 
                class="rounded border border-gray-400 text-sm w-36 px-2 py-1 focus:outline-none focus:border-black focus:ring-1 focus:ring-black"
            >
        </span>

        <!-- Submit Button -->
        <button 
            type="submit" 
            class="rounded border border-black bg-black text-white px-4 py-1.5 text-sm hover:bg-gray-800 transition"
        >
            Apply
        </button>
    </form>
</div>

<!-- Date Range Indicator -->
<div class="mb-4">
    <p class="text-xs text-gray-500">
        Showing data for: 
        <span class="font-medium text-black">
            @if($range === 'today')
                Today
            @elseif($range === '7d')
                Last 7 days
            @elseif($range === '30d')
                Last 30 days
            @else
                {{ \Carbon\Carbon::parse($dateStart)->format('M j, Y') }} - {{ \Carbon\Carbon::parse($dateEnd)->format('M j, Y') }}
            @endif
        </span>
    </p>
</div>

<!-- Metrics Overview -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4 mb-10">
    <!-- Total Revenue -->
    <div class="bg-white border border-gray-200 p-6">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">Total Revenue</p>
        <p class="text-3xl font-light text-black">{{ number_format($metrics['total_revenue']['value'], 2) }}</p>
        <div class="flex items-center gap-2 mt-1">
            <p class="text-xs text-gray-400">NPR</p>
            @if($metrics['total_revenue']['change'] != 0)
                <span class="text-xs {{ $metrics['total_revenue']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $metrics['total_revenue']['change'] > 0 ? '+' : '' }}{{ $metrics['total_revenue']['change'] }}%
                </span>
            @endif
        </div>
    </div>

    <!-- Total Transactions -->
    <div class="bg-white border border-gray-200 p-6">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">Total Transactions</p>
        <p class="text-3xl font-light text-black">{{ number_format($metrics['total_transactions']['value']) }}</p>
        <div class="flex items-center gap-2 mt-1">
            @if($metrics['total_transactions']['change'] != 0)
                <span class="text-xs {{ $metrics['total_transactions']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $metrics['total_transactions']['change'] > 0 ? '+' : '' }}{{ $metrics['total_transactions']['change'] }}%
                </span>
            @endif
        </div>
    </div>

    <!-- AOV -->
    <div class="bg-white border border-gray-200 p-6">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">AOV</p>
        <p class="text-3xl font-light text-black">{{ number_format($metrics['aov']['value'], 2) }}</p>
         <div class="flex items-center gap-2 mt-1">
            <p class="text-xs text-gray-400">NPR</p>
            @if($metrics['aov']['change'] != 0)
                <span class="text-xs {{ $metrics['aov']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $metrics['aov']['change'] > 0 ? '+' : '' }}{{ $metrics['aov']['change'] }}%
                </span>
            @endif
        </div>
    </div>

    <!-- Customer Count -->
    <div class="bg-white border border-gray-200 p-6">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">Active Customers</p>
        <p class="text-3xl font-light text-black">{{ number_format($metrics['customer_count']['value']) }}</p>
        <div class="flex items-center gap-2 mt-1">
            @if($metrics['customer_count']['change'] != 0)
                <span class="text-xs {{ $metrics['customer_count']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $metrics['customer_count']['change'] > 0 ? '+' : '' }}{{ $metrics['customer_count']['change'] }}%
                </span>
            @endif
        </div>
    </div>
    
     <!-- All-time Customers -->
    <div class="bg-white border border-gray-200 p-6">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">All-time Customers</p>
        <p class="text-3xl font-light text-black">{{ number_format($metrics['all_time_customers']['value']) }}</p>
    </div>

    <!-- New Customer Revenue -->
    <div class="bg-white border border-gray-200 p-6">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">New Cust. Revenue</p>
        <p class="text-3xl font-light text-black">{{ number_format($metrics['new_customer_revenue']['value'], 2) }}</p>
         <div class="flex items-center gap-2 mt-1">
            <p class="text-xs text-gray-400">NPR</p>
             @if($metrics['new_customer_revenue']['change'] != 0)
                <span class="text-xs {{ $metrics['new_customer_revenue']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $metrics['new_customer_revenue']['change'] > 0 ? '+' : '' }}{{ $metrics['new_customer_revenue']['change'] }}%
                </span>
            @endif
        </div>
    </div>

    <!-- Returning Customer Revenue -->
    <div class="bg-white border border-gray-200 p-6">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">Ret. Cust. Revenue</p>
        <p class="text-3xl font-light text-black">{{ number_format($metrics['returning_customer_revenue']['value'], 2) }}</p>
         <div class="flex items-center gap-2 mt-1">
            <p class="text-xs text-gray-400">NPR</p>
             @if($metrics['returning_customer_revenue']['change'] != 0)
                <span class="text-xs {{ $metrics['returning_customer_revenue']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $metrics['returning_customer_revenue']['change'] > 0 ? '+' : '' }}{{ $metrics['returning_customer_revenue']['change'] }}%
                </span>
            @endif
        </div>
    </div>
    
    <!-- New Customers -->
    <div class="bg-white border border-gray-200 p-6">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">New Customers</p>
        <p class="text-3xl font-light text-black">{{ number_format($metrics['new_customers']['value']) }}</p>
        <div class="flex items-center gap-2 mt-1">
             @if($metrics['new_customers']['change'] != 0)
                <span class="text-xs {{ $metrics['new_customers']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $metrics['new_customers']['change'] > 0 ? '+' : '' }}{{ $metrics['new_customers']['change'] }}%
                </span>
            @endif
        </div>
    </div>
    
    <!-- Returning Customers -->
    <div class="bg-white border border-gray-200 p-6">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">Returning Customers</p>
        <p class="text-3xl font-light text-black">{{ number_format($metrics['returning_customers']['value']) }}</p>
        <div class="flex items-center gap-2 mt-1">
             @if($metrics['returning_customers']['change'] != 0)
                <span class="text-xs {{ $metrics['returning_customers']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $metrics['returning_customers']['change'] > 0 ? '+' : '' }}{{ $metrics['returning_customers']['change'] }}%
                </span>
            @endif
        </div>
    </div>

</div>

<div class="grid grid-cols-1 gap-10">
    <!-- Product Performance -->
    <div class="space-y-4">
        <h2 class="text-lg font-medium text-gray-700">Product Category Performance</h2>
        <div class="bg-white border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table id="productTable" class="min-w-full divide-y divide-gray-200 display" style="width:100%">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-gray-500 font-medium">Item Name</th>
                            <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-gray-500 font-medium">Total Revenue</th>
                            <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-gray-500 font-medium">Total Orders</th>
                            <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-gray-500 font-medium">Total Quantity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach($productPerformance as $product)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 text-sm font-medium text-black">{{ $product->title }}</td>
                                <td class="px-6 py-4 text-sm text-right text-black">{{ number_format($product->total_revenue, 2) }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-600">{{ $product->total_orders }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-600">{{ $product->total_quantity }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Customer Comparison -->
    <div class="space-y-4">
        <h2 class="text-lg font-medium text-gray-700">Customer Comparison</h2>
        <div class="bg-white border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table id="customerTable" class="min-w-full divide-y divide-gray-200 display" style="width:100%">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs uppercase tracking-wider text-gray-500 font-medium">Customer Name</th>
                            <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-gray-500 font-medium">Total Sales</th>
                            <th class="px-6 py-4 text-right text-xs uppercase tracking-wider text-gray-500 font-medium">Total Orders</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                       @foreach($customerPerformance as $customer)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 text-sm font-medium text-gray-700">
                                    {{ $customer->customer ? $customer->customer->full_name : ($customer->email ?? 'Guest') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-right text-black">{{ number_format($customer->total_sales, 2) }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-600">{{ $customer->total_orders }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>



@push('scripts')
<script>
    $(document).ready(function() {
        $('#productTable').DataTable({
            order: [[1, 'desc']], // Sort by Revenue by default
            pageLength: 10,
            language: {
                search: "",
                searchPlaceholder: "Search products..."
            }
        });

        $('#customerTable').DataTable({
            order: [[1, 'desc']], // Sort by Sales by default
            pageLength: 10,
            language: {
                search: "",
                searchPlaceholder: "Search customers..."
            }
        });
    });
</script>
@endpush
</div>
@endsection
