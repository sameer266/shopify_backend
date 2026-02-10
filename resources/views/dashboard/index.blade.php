@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div x-data="dashboardCharts({{ json_encode([
    'dailySales' => $dailySales,
    'orderStatusDistribution' => $orderStatusDistribution,
    'topProducts' => $mostSoldProducts,
    'dateStart' => $dateStart,
    'dateEnd' => $dateEnd,
    'range' => $range,
    'chartDataUrl' => route('dashboard.chart-data'),
]) }})">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <h1 class="text-3xl font-light text-black tracking-tight">
            Dashboard
        </h1>
     <form method="get" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-2">
    <!-- Range Select -->
    <select 
        name="range" 
        class="rounded border border-gray-400 text-sm px-2 py-1 focus:outline-none focus:border-black focus:ring-1 focus:ring-black"
        @change="updateRange($event)"
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

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4 mb-10">
        <div class="bg-white border border-gray-200 p-6">
            <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">Total Orders</p>
            <p class="text-3xl font-light text-black">{{ number_format($metrics['total_orders']) }}</p>
        </div>
        <div class="bg-white border border-gray-200 p-6">
            <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">Net Sales</p>
            <p class="text-3xl font-light text-black">{{ number_format($metrics['net_sales'], 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">NPR</p>
        </div>
        <div class="bg-white border border-gray-200 p-6">
            <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">Gross Sales</p>
            <p class="text-3xl font-light text-black">{{ number_format($metrics['total_gross_sales'], 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">NPR</p>
        </div>
        <div class="bg-white border border-gray-200 p-6">
            <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">Returns</p>
            <p class="text-3xl font-light text-black">{{ number_format($metrics['returns'], 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">NPR</p>
        </div>
     
        <div class="bg-white border border-gray-200 p-6">
            <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">Returning Customer</p>
            <p class="text-3xl font-light text-black">{{ number_format($metrics['returning_customers']) }}</p>
        </div>
        <div class="bg-white border border-gray-200 p-6">
            <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">New Customer (30d)</p>
            <p class="text-3xl font-light text-black">{{ number_format($metrics['new_customers']) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-10">
        <div class="bg-white border border-gray-200 p-8">
            <h2 class="text-sm uppercase tracking-wider text-gray-500 mb-6">Sales (last 30 days)</h2>
            <div class="h-72"><canvas id="chartSales"></canvas></div>
        </div>
        <div class="bg-white border border-gray-200 p-8">
            <h2 class="text-sm uppercase tracking-wider text-gray-500 mb-6">Order Status</h2>
            <div class="h-72 flex items-center justify-center"><canvas id="chartOrderStatus"></canvas></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-10">
        <div class="bg-white border border-gray-200 p-8">
            <h2 class="text-sm uppercase tracking-wider text-gray-500 mb-6">Top Selling Products</h2>
            <div class="h-72"><canvas id="chartTopProducts"></canvas></div>
        </div>
        <div class="bg-white border border-gray-200 p-8">
            <h2 class="text-sm uppercase tracking-wider text-gray-500 mb-6">Order Insights</h2>
            <div class="space-y-6">
                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-400 mb-3">Financial Status</p>
                    <div class="flex flex-wrap gap-3">
                        @foreach($orderInsights['by_financial_status'] ?? [] as $status => $count)
                            <span class="text-xs border border-gray-300 px-3 py-1">{{ ucfirst($status) }}: {{ $count }}</span>
                        @endforeach
                        @if(empty($orderInsights['by_financial_status'])) <span class="text-gray-400 text-sm">No data</span> @endif
                    </div>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-400 mb-3">Fulfillment</p>
                    <div class="flex flex-wrap gap-3">
                        @foreach($orderInsights['by_fulfillment_status'] ?? [] as $status => $count)
                            <span class="text-xs border border-gray-300 px-3 py-1">{{ ucfirst($status) }}: {{ $count }}</span>
                        @endforeach
                        @if(empty($orderInsights['by_fulfillment_status'])) <span class="text-gray-400 text-sm">No data</span> @endif
                    </div>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-400 mb-3">Payment</p>
                    <span class="text-sm text-gray-700">Paid: {{ $orderInsights['by_is_paid'][1] ?? 0 }} / Unpaid: {{ $orderInsights['by_is_paid'][0] ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
        <div class="bg-white border border-gray-200 p-8">
            <h2 class="text-sm uppercase tracking-wider text-gray-500 mb-6">Returning Customers</h2>
            @if(!$returningCustomersList)
                <x-empty-state title="No returning customers" />
            @else
                <ul class="space-y-3 text-sm">
                    @foreach($returningCustomersList as $c)
                        <li class="flex justify-between items-center pb-3 border-b border-gray-100 last:border-0">
                            <span class="text-gray-700">{{ $c->full_name }}</span>
                            <span class="text-black">{{ $c->order_count }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="bg-white border border-gray-200 p-8">
            <h2 class="text-sm uppercase tracking-wider text-gray-500 mb-6">Top by Spend</h2>
            @if(!$topCustomersBySpend)
                <x-empty-state title="No data" />
            @else
                <ul class="space-y-3 text-sm">
                    @foreach($topCustomersBySpend as $row)
                        <li class="flex justify-between items-center pb-3 border-b border-gray-100 last:border-0">
                            <span class="text-gray-700">{{ $row->customer?->full_name ?? 'Guest' }}</span>
                            <span class="text-black font-medium">{{ number_format($row->total_spend, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="bg-white border border-gray-200 p-8">
            <h2 class="text-sm uppercase tracking-wider text-gray-500 mb-6">New Customers</h2>
            @if(!$newCustomersList)
                <x-empty-state title="No new customers" />
            @else
                <ul class="space-y-3 text-sm">
                    @foreach($newCustomersList as $c)
                        <li class="flex justify-between items-center pb-3 border-b border-gray-100 last:border-0">
                            <span class="text-gray-700">{{ $c->full_name }}</span>
                            <span class="text-gray-400 text-xs">{{ $c->created_at->format('M j') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
        <div class="bg-white border border-gray-200 p-8">
            <h2 class="text-sm uppercase tracking-wider text-gray-500 mb-6">Most Sold Products</h2>
            @if(!$mostSoldProducts)
                <x-empty-state title="No orders yet" />
            @else
                <ul class="space-y-3 text-sm">
                    @foreach($mostSoldProducts as $p)
                        <li class="flex justify-between items-center pb-3 border-b border-gray-100 last:border-0">
                            <span class="text-gray-700 truncate max-w-[200px]">{{ $p['title'] }}</span>
                            <span class="text-black">{{ $p['total_quantity'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="bg-white border border-gray-200 p-8">
            <h2 class="text-sm uppercase tracking-wider text-gray-500 mb-6">Highest Revenue</h2>
            @if(!$highestRevenueProducts)
                <x-empty-state title="No orders yet" />
            @else
                <ul class="space-y-3 text-sm">
                    @foreach($highestRevenueProducts as $p)
                        <li class="flex justify-between items-center pb-3 border-b border-gray-100 last:border-0">
                            <span class="text-gray-700 truncate max-w-[200px]">{{ $p['title'] }}</span>
                            <span class="text-black font-medium">{{ $p['revenue'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="bg-white border border-gray-200 p-8">
            <h2 class="text-sm uppercase tracking-wider text-gray-500 mb-6">Most Refunded</h2>
            @if($mostRefundedProducts->isEmpty())
                <x-empty-state title="No refunds yet" />
            @else
                <ul class="space-y-3 text-sm">
                    @foreach($mostRefundedProducts as $p)
                        <li class="flex justify-between items-center pb-3 border-b border-gray-100 last:border-0">
                            <span class="text-gray-700 truncate max-w-[200px]">{{ $p['title'] }}</span>
                            <div class="text-right">
                                <span class="block text-red-600 font-medium">-{{ number_format($p['amount'], 2) }}</span>
                                <span class="text-gray-400 text-xs">Qty: {{ $p['quantity'] }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="bg-white border border-gray-200 p-6 mb-6">
        <p class="text-sm text-gray-600">Average order value: <strong class="text-black ml-2">NPR {{ number_format($metrics['average_order_value'], 2) }}</strong></p>
    </div>

    <div id="sync" class="bg-white border border-gray-200 p-8">
        <h2 class="text-sm uppercase tracking-wider text-gray-500 mb-2">Shopify Sync</h2>
        <p class="text-sm text-gray-500 mb-6">Sync orders from Shopify. Webhooks keep data updated.</p>
        <div class="flex flex-wrap items-center gap-4">
            <form method="post" action="{{ route('sync.orders') }}" class="inline" x-data="{ loading: false }" @submit="loading = true">
                @csrf
                <button type="submit" :disabled="loading" :class="{ 'opacity-75 cursor-wait': loading }" class="border border-black bg-black text-white px-6 py-2 text-sm hover:bg-gray-800 transition flex items-center gap-2">
                    <span x-show="loading" class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-white"></span>
                    <span x-text="loading ? 'Syncing...' : 'Sync Orders'"></span>
                </button>
            </form>
         @if($lastSyncTime)
    @php
        $time = \Carbon\Carbon::parse($lastSyncTime);
    @endphp
    <span class="text-sm text-gray-500">
        Last sync: {{ $time->diffForHumans() }} ({{ $time->format('M j, Y, h:i A') }})
    </span>
@else
    <span class="text-sm text-gray-400">Not synced yet</span>
@endif

        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('dashboardCharts', (config) => ({
        dailySales: config.dailySales || [],
        orderStatusDistribution: config.orderStatusDistribution || [],
        topProducts: config.topProducts || [],
        range: config.range || '30d',
        chartSales: null,
        chartOrderStatus: null,
        chartTopProducts: null,
        init() { this.$nextTick(() => { this.drawCharts(); }); },
        drawCharts() {
            // Destroy old charts if exist
            if (this.chartSales) this.chartSales.destroy();
            if (this.chartOrderStatus) this.chartOrderStatus.destroy();
            if (this.chartTopProducts) this.chartTopProducts.destroy();

            // --- Daily Sales Line Chart (Black & White) ---
            const salesCtx = document.getElementById('chartSales');
            if (salesCtx) {
                this.chartSales = new Chart(salesCtx, {
                    type: 'line',
                    data: { 
                        labels: this.dailySales.map(d => d.date), 
                        datasets: [{ 
                            label: 'Sales', 
                            data: this.dailySales.map(d => d.total), 
                            borderColor: '#000000', // black line
                            backgroundColor: 'rgba(0,0,0,0.05)', // light gray fill
                            fill: true, 
                            tension: 0.2,
                            borderWidth: 2,
                            pointBackgroundColor: '#000000', // black dots
                            pointBorderColor: '#000000'
                        }] 
                    },
                    options: { 
                        responsive: true, 
                        maintainAspectRatio: false, 
                        plugins: { legend: { display: false } }, 
                        scales: { 
                            y: { 
                                beginAtZero: true,
                                grid: { color: '#d1d5db' }, // gray grid
                                ticks: { color: '#000000' } // black text
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#000000' } // black text
                            }
                        } 
                    }
                });
            }

            // --- Order Status Pie Chart (Colorful) ---
            const statusCtx = document.getElementById('chartOrderStatus');
            if (statusCtx && this.orderStatusDistribution.length) {
                this.chartOrderStatus = new Chart(statusCtx, {
                    type: 'pie',
                    data: { 
                        labels: this.orderStatusDistribution.map(d => d.label), 
                        datasets: [{ 
                            data: this.orderStatusDistribution.map(d => d.count), 
                            backgroundColor: [
                                '#10B981', // Green
                                '#EF4444', // Red
                                '#F59E0B', // Amber
                                '#3B82F6', // Blue
                                '#8B5CF6'  // Purple
                            ],
                            borderWidth: 1
                        }] 
                    },
                    options: { 
                        responsive: true, 
                        maintainAspectRatio: false, 
                        plugins: { 
                            legend: { 
                                position: 'right',
                                labels: { color: '#000000', font: { size: 11 } } // black text
                            } 
                        } 
                    }
                });
            }

            // --- Top Products Bar Chart (Black & White) ---
            const topCtx = document.getElementById('chartTopProducts');
            if (topCtx && this.topProducts.length) {
                this.chartTopProducts = new Chart(topCtx, {
                    type: 'bar',
                    data: { 
                        labels: this.topProducts.map(p => p.title.length > 20 ? p.title.slice(0, 20) + '…' : p.title), 
                        datasets: [{ 
                            label: 'Sold', 
                            data: this.topProducts.map(p => Number(p.total_quantity)), 
                            backgroundColor: '#000000', // black bars
                            borderColor: '#000000',
                            borderWidth: 1
                        }] 
                    },
                    options: { 
                        indexAxis: 'y', 
                        responsive: true, 
                        maintainAspectRatio: false, 
                        plugins: { legend: { display: false } },
                        scales: { 
                            x: { 
                                beginAtZero: true,
                                grid: { color: '#d1d5db' }, // gray grid
                                ticks: { color: '#000000' } // black text
                            },
                            y: {
                                grid: { display: false },
                                ticks: { color: '#000000', font: { size: 11 } } // black text
                            }
                        } 
                    }
                });
            }
        },
        updateRange(ev) { this.range = ev.target.value; }
    }));
});
</script>

@endpush
@endsection