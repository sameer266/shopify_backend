@extends('layouts.app')

@section('title', 'Reports & Analytics')

@section('content')
<div class="space-y-8">
    <!-- Header Section -->
    <div x-data="{ range: '{{ $range }}', showCustom: '{{ $range }}' === 'custom' }" 
         class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-light text-black tracking-tight mb-1">Reports & Analytics</h1>
            <p class="text-sm text-gray-500">
                @if($range === 'today')
                    {{ now()->format('F j, Y') }}
                @elseif($range === '7d')
                    {{ now()->subDays(6)->format('M j') }} - {{ now()->format('M j, Y') }}
                @elseif($range === '30d')
                    {{ now()->subDays(29)->format('M j') }} - {{ now()->format('M j, Y') }}
                @else
                    {{ \Carbon\Carbon::parse($dateStart)->format('M j, Y') }} - {{ \Carbon\Carbon::parse($dateEnd)->format('M j, Y') }}
                @endif
            </p>
        </div>
        
        <form method="get" action="{{ route('reports.index') }}" class="flex flex-wrap items-center gap-2">
            <select 
                name="range" 
                class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-black focus:ring-1 focus:ring-black"
                @change="if($event.target.value !== 'custom') { $event.target.form.submit() } else { showCustom = true }"
            >
                <option value="today" {{ $range === 'today' ? 'selected' : '' }}>Today</option>
                <option value="7d" {{ $range === '7d' ? 'selected' : '' }}>Last 7 days</option>
                <option value="30d" {{ $range === '30d' ? 'selected' : '' }}>Last 30 days</option>
                <option value="90d" {{ $range === '90d' ? 'selected' : '' }}>Last 90 days</option>
                <option value="custom" {{ $range === 'custom' ? 'selected' : '' }}>Custom range</option>
            </select>

            <div x-show="showCustom" x-cloak class="flex items-center gap-2">
                <input 
                    type="date" 
                    name="start" 
                    value="{{ $dateStart }}" 
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-black focus:ring-1 focus:ring-black"
                >
                <span class="text-gray-400">—</span>
                <input 
                    type="date" 
                    name="end" 
                    value="{{ $dateEnd }}" 
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-black focus:ring-1 focus:ring-black"
                >
            </div>

            <button 
                type="submit" 
                class="rounded-md bg-black px-4 py-2 text-sm text-white hover:bg-gray-800 transition-colors"
            >
                Apply
            </button>
            
            @if($range === 'custom')
                <a href="{{ route('reports.index', ['range' => '30d']) }}" 
                   class="text-sm text-gray-500 hover:text-black transition-colors">
                    Clear
                </a>
            @endif
        </form>
    </div>

    <!-- Key Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Revenue Card -->
        <div class="bg-white border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Revenue</span>
                <span class="text-xs text-gray-400">NPR</span>
            </div>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-light text-black">
                    {{ number_format($metrics['total_revenue']['value'], 2) }}
                </span>
                @if($metrics['total_revenue']['change'] != 0)
                    <span class="text-sm {{ $metrics['total_revenue']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $metrics['total_revenue']['change'] > 0 ? '+' : '' }}{{ $metrics['total_revenue']['change'] }}%
                    </span>
                @endif
            </div>
        </div>

        <!-- Transactions Card -->
        <div class="bg-white border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Transactions</span>
                <span class="text-xs text-gray-400">count</span>
            </div>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-light text-black">
                    {{ number_format($metrics['total_transactions_count']['value']) }}
                </span>
              
            </div>
        </div>
     

        <!-- AOV Card -->
        <div class="bg-white border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Average Order Value</span>
                <span class="text-xs text-gray-400">NPR</span>
            </div>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-light text-black">
                    {{ number_format($metrics['aov']['value'], 2) }}
                </span>
                @if($metrics['aov']['change'] != 0)
                    <span class="text-sm {{ $metrics['aov']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $metrics['aov']['change'] > 0 ? '+' : '' }}{{ $metrics['aov']['change'] }}%
                    </span>
                @endif
            </div>
        </div>

        <!-- Active Customers Card -->
        <div class="bg-white border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Active Customers</span>
                <span class="text-xs text-gray-400">/ {{ number_format($metrics['all_time_customers']['value']) }} total</span>
            </div>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-light text-black">
                    {{ number_format($metrics['customer_count']['value']) }}
                </span>
                @if($metrics['customer_count']['change'] != 0)
                    <span class="text-sm {{ $metrics['customer_count']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $metrics['customer_count']['change'] > 0 ? '+' : '' }}{{ $metrics['customer_count']['change'] }}%
                    </span>
                @endif
            </div>
        </div>
    </div>




    <!-- Key Metrics Row -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-5">
    <!-- Total Transactions (NPR) -->
    <div class="bg-white border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Transactions (NPR)</span>
        </div>
        <div class="flex items-baseline justify-between">
            <span class="text-2xl font-light text-black">
                {{ number_format($metrics['total_transactions_amount']['value'], 2) }}
            </span>
            @if($metrics['total_transactions_amount']['change'] != 0)
                <span class="text-sm {{ $metrics['total_transactions_amount']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $metrics['total_transactions_amount']['change'] > 0 ? '+' : '' }}{{ $metrics['total_transactions_amount']['change'] }}%
                </span>
            @endif
        </div>
    </div>

    <!-- All-Time Customers -->
    <div class="bg-white border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">All-Time Customers</span>
        </div>
        <div class="flex items-baseline justify-between">
            <span class="text-2xl font-light text-black">
                {{ number_format($metrics['all_time_customers']['value']) }}
            </span>
            @if($metrics['all_time_customers']['change'] != 0)
                <span class="text-sm {{ $metrics['all_time_customers']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $metrics['all_time_customers']['change'] > 0 ? '+' : '' }}{{ $metrics['all_time_customers']['change'] }}%
                </span>
            @endif
        </div>
    </div>

    <!-- New Customers -->
    <div class="bg-white border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">New Customers</span>
        </div>
        <div class="flex items-baseline justify-between">
            <span class="text-2xl font-light text-black">
                {{ number_format($metrics['new_customers']['value']) }}
            </span>
            @if($metrics['new_customers']['change'] != 0)
                <span class="text-sm {{ $metrics['new_customers']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $metrics['new_customers']['change'] > 0 ? '+' : '' }}{{ $metrics['new_customers']['change'] }}%
                </span>
            @endif
        </div>
    </div>

    <!-- Returning Customers -->
    <div class="bg-white border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Returning Customers</span>
        </div>
        <div class="flex items-baseline justify-between">
            <span class="text-2xl font-light text-black">
                {{ number_format($metrics['returning_customers']['value']) }}
            </span>
            @if($metrics['returning_customers']['change'] != 0)
                <span class="text-sm {{ $metrics['returning_customers']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $metrics['returning_customers']['change'] > 0 ? '+' : '' }}{{ $metrics['returning_customers']['change'] }}%
                </span>
            @endif
        </div>
    </div>
</div>




    <!-- Customer Revenue Split -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- New Customer Revenue -->
        <div class="bg-white border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <span class="text-xs font-medium uppercase tracking-wider text-gray-500">New Customers</span>
                    <p class="text-sm text-gray-500 mt-1">{{ number_format($metrics['new_customers']['value']) }} customers</p>
                </div>
                @if($metrics['new_customers']['change'] != 0)
                    <span class="text-sm {{ $metrics['new_customers']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $metrics['new_customers']['change'] > 0 ? '+' : '' }}{{ $metrics['new_customers']['change'] }}%
                    </span>
                @endif
            </div>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-light text-black">
                    {{ number_format($metrics['new_customer_revenue']['value'], 2) }}
                </span>
                <span class="text-xs text-gray-400">NPR revenue</span>
            </div>
            <div class="mt-3 pt-3 border-t border-gray-100">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">% of total</span>
                    @php
                        $totalRev = $metrics['total_revenue']['value'];
                        $newRev = $metrics['new_customer_revenue']['value'];
                        $newPercentage = $totalRev > 0 ? round(($newRev / $totalRev) * 100, 1) : 0;
                    @endphp
                    <span class="font-medium text-black">{{ $newPercentage }}%</span>
                </div>
            </div>
        </div>

        <!-- Returning Customer Revenue -->
        <div class="bg-white border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Returning Customers</span>
                    <p class="text-sm text-gray-500 mt-1">{{ number_format($metrics['returning_customers']['value']) }} customers</p>
                </div>
                @if($metrics['returning_customers']['change'] != 0)
                    <span class="text-sm {{ $metrics['returning_customers']['change'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $metrics['returning_customers']['change'] > 0 ? '+' : '' }}{{ $metrics['returning_customers']['change'] }}%
                    </span>
                @endif
            </div>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-light text-black">
                    {{ number_format($metrics['returning_customer_revenue']['value'], 2) }}
                </span>
                <span class="text-xs text-gray-400">NPR revenue</span>
            </div>
            <div class="mt-3 pt-3 border-t border-gray-100">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">% of total</span>
                    @php
                        $returnRev = $metrics['returning_customer_revenue']['value'];
                        $returnPercentage = $totalRev > 0 ? round(($returnRev / $totalRev) * 100, 1) : 0;
                    @endphp
                    <span class="font-medium text-black">{{ $returnPercentage }}%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Chart -->
    @if(!empty($chartData['labels']))
    <div class="bg-white border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-sm font-medium uppercase tracking-wider text-gray-700">Revenue Overview</h2>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 bg-black rounded-full"></span>
                    <span class="text-xs text-gray-600">Revenue</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                    <span class="text-xs text-gray-600">Orders</span>
                </div>
            </div>
        </div>
        <div class="h-64">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
    @endif

    <!-- Product Performance - DataTable -->
    <div class="bg-white border border-gray-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-sm font-medium uppercase tracking-wider text-gray-700">Top Products</h2>
                <p class="text-xs text-gray-500 mt-1">Best performing products by revenue</p>
            </div>
        </div>
        <div class="p-4">
            <table id="productTable" class="min-w-full divide-y divide-gray-200 display" style="width:100%">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                        <th class="px-6 py-4 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Revenue</th>
                        <th class="px-6 py-4 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Orders</th>
                        <th class="px-6 py-4 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Quantity</th>
                        <th class="px-6 py-4 text-right text-xs font-medium uppercase tracking-wider text-gray-500">AOV</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($productPerformance as $product)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm text-black">{{ $product->title }}</td>
                            <td class="px-6 py-4 text-sm text-right text-black">{{ number_format($product->total_revenue, 2) }}</td>
                            <td class="px-6 py-4 text-sm text-right text-gray-600">{{ number_format($product->total_orders) }}</td>
                            <td class="px-6 py-4 text-sm text-right text-gray-600">{{ number_format($product->total_quantity) }}</td>
                            <td class="px-6 py-4 text-sm text-right text-black">
                                @if($product->total_orders > 0)
                                    {{ number_format($product->total_revenue / $product->total_orders, 2) }}
                                @else
                                    0.00
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-sm text-center text-gray-500">
                                No product data available for this period
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Customer Performance - DataTable -->
    <div class="bg-white border border-gray-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-sm font-medium uppercase tracking-wider text-gray-700">Top Customers</h2>
                <p class="text-xs text-gray-500 mt-1">Highest spending customers</p>
            </div>
        </div>
        <div class="p-4">
            <table id="customerTable" class="min-w-full divide-y divide-gray-200 display" style="width:100%">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Customer</th>
                        <th class="px-6 py-4 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total Sales</th>
                        <th class="px-6 py-4 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Orders</th>
                        <th class="px-6 py-4 text-right text-xs font-medium uppercase tracking-wider text-gray-500">AOV</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($customerPerformance as $customer)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-black">
                                    {{ $customer->customer ? $customer->customer->full_name : 'Guest' }}
                                </div>
                                @if($customer->email)
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $customer->email }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-black">{{ number_format($customer->total_sales, 2) }}</td>
                            <td class="px-6 py-4 text-sm text-right text-gray-600">{{ number_format($customer->total_orders) }}</td>
                            <td class="px-6 py-4 text-sm text-right text-black">
                                @if($customer->total_orders > 0)
                                    {{ number_format($customer->total_sales / $customer->total_orders, 2) }}
                                @else
                                    0.00
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-sm text-center text-gray-500">
                                No customer data available for this period
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($customerPerformance->count() >= 100)
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
            <p class="text-xs text-gray-500 text-center">
                Showing top 100 customers. Export for complete list.
            </p>
        </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
    /* DataTables Custom Styling - Black/White/Gray Theme */
    div.dataTables_wrapper div.dataTables_length select {
        @apply rounded-md border border-gray-300 bg-white px-2 py-1 text-sm text-gray-700 focus:border-black focus:ring-1 focus:ring-black;
        width: auto;
        min-width: 60px;
    }
    
    div.dataTables_wrapper div.dataTables_filter input {
        @apply rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-black focus:ring-1 focus:ring-black;
        margin-left: 0.5rem;
        width: 200px;
    }
    
    div.dataTables_wrapper div.dataTables_paginate {
        @apply flex items-center gap-2 mt-4;
    }
    
    div.dataTables_wrapper .paginate_button {
        @apply px-3 py-1.5 text-sm border border-gray-300 bg-white text-gray-700 rounded-md hover:bg-gray-50 hover:border-gray-400 transition-colors cursor-pointer;
    }
    
    div.dataTables_wrapper .paginate_button.current {
        @apply bg-black text-white border-black hover:bg-gray-800;
    }
    
    div.dataTables_wrapper .paginate_button.disabled {
        @apply opacity-50 cursor-not-allowed hover:bg-white hover:border-gray-300;
    }
    
    div.dataTables_wrapper .dataTables_info {
        @apply text-sm text-gray-600 py-2;
    }
    
    div.dataTables_wrapper .dataTables_processing {
        @apply bg-black bg-opacity-5 text-black;
    }
    
    /* Remove default DataTables styling */
    table.dataTable thead .sorting:after,
    table.dataTable thead .sorting_asc:after,
    table.dataTable thead .sorting_desc:after {
        @apply text-gray-400;
    }
    
    table.dataTable thead th {
        @apply border-b-2 border-gray-200;
    }
    
    table.dataTable.no-footer {
        @apply border-b border-gray-200;
    }
    
    /* Responsive */
    @media screen and (max-width: 640px) {
        div.dataTables_wrapper div.dataTables_filter input {
            width: 150px;
        }
    }
</style>
@endpush

@push('scripts')
@if(!empty($chartData['labels']))
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('revenueChart').getContext('2d');
        
        // Format currency for tooltips
        const formatNPR = (value) => {
            return 'NPR ' + new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value);
        };

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: @json($chartData['labels']),
                datasets: [
                    {
                        label: 'Revenue',
                        data: @json($chartData['revenue']),
                        borderColor: '#000000',
                        backgroundColor: 'rgba(0, 0, 0, 0.02)',
                        borderWidth: 1.5,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#000000',
                        yAxisID: 'y-revenue',
                        tension: 0.1,
                        fill: false
                    },
                    {
                        label: 'Orders',
                        data: @json($chartData['orders']),
                        borderColor: '#9CA3AF',
                        backgroundColor: 'rgba(156, 163, 175, 0.02)',
                        borderWidth: 1.5,
                        pointRadius: 2,
                        pointHoverRadius: 4,
                        pointBackgroundColor: '#9CA3AF',
                        yAxisID: 'y-orders',
                        tension: 0.1,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#ffffff',
                        titleColor: '#111827',
                        bodyColor: '#6B7280',
                        borderColor: '#E5E7EB',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.label === 'Revenue') {
                                    label += formatNPR(context.raw);
                                } else {
                                    label += context.raw;
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    'y-revenue': {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        grid: {
                            color: '#F3F4F6',
                            drawBorder: false,
                        },
                        ticks: {
                            callback: function(value) {
                                return 'NPR ' + (value / 1000) + 'k';
                            },
                            color: '#6B7280',
                        },
                        title: {
                            display: true,
                            text: 'Revenue (NPR)',
                            color: '#6B7280',
                            font: {
                                size: 11,
                                weight: 'normal'
                            }
                        }
                    },
                    'y-orders': {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            display: false,
                        },
                        ticks: {
                            color: '#6B7280',
                        },
                        title: {
                            display: true,
                            text: 'Orders',
                            color: '#6B7280',
                            font: {
                                size: 11,
                                weight: 'normal'
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6B7280',
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });
    });
</script>
@endif

<script>
    $(document).ready(function() {
        // Initialize Product DataTable
        if ($('#productTable tbody tr').length > 1 || ($('#productTable tbody tr').length === 1 && !$('#productTable tbody td[colspan]').length)) {
            $('#productTable').DataTable({
                order: [[1, 'desc']], // Sort by Revenue by default
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                language: {
                    search: "",
                    searchPlaceholder: "Search products...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ products",
                    infoEmpty: "Showing 0 to 0 of 0 products",
                    infoFiltered: "(filtered from _MAX_ total products)",
                    
                },
                dom: '<"flex flex-wrap items-center justify-between gap-4 mb-4"<"dataTables_length"l><"dataTables_filter"f>>rt<"flex flex-wrap items-center justify-between gap-4 mt-4"<"dataTables_info"i><"dataTables_paginate"p>>',
                initComplete: function() {
                    // Style the search input
                    $('.dataTables_filter input').attr('placeholder', 'Search products...');
                }
            });
        }

        // Initialize Customer DataTable
        if ($('#customerTable tbody tr').length > 1 || ($('#customerTable tbody tr').length === 1 && !$('#customerTable tbody td[colspan]').length)) {
            $('#customerTable').DataTable({
                order: [[1, 'desc']], // Sort by Sales by default
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                language: {
                    search: "",
                    searchPlaceholder: "Search customers...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ customers",
                    infoEmpty: "Showing 0 to 0 of 0 customers",
                    infoFiltered: "(filtered from _MAX_ total customers)",
                   
                },
                dom: '<"flex flex-wrap items-center justify-between gap-4 mb-4"<"dataTables_length"l><"dataTables_filter"f>>rt<"flex flex-wrap items-center justify-between gap-4 mt-4"<"dataTables_info"i><"dataTables_paginate"p>>',
                initComplete: function() {
                    // Style the search input
                    $('.dataTables_filter input').attr('placeholder', 'Search customers...');
                }
            });
        }
    });
</script>
@endpush
