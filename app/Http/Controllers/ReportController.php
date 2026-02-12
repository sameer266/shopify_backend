<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // Get date range
        [$startDate, $endDate] = $this->getDateRange($request);

        // Current period metrics
        $currentMetrics = $this->calculateMetrics($startDate, $endDate);

        // Previous year metrics (same period last year)
        $lastYearStart = $startDate->copy()->subYear();
        $lastYearEnd = $endDate->copy()->subYear();
        $lastYearMetrics = $this->calculateMetrics($lastYearStart, $lastYearEnd);

        // Calculate percentage changes
        $metrics = $this->calculateChanges($currentMetrics, $lastYearMetrics);

        // Product Performance
        $productPerformance = OrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('processed_at', [$startDate, $endDate])
                  ->whereIn('financial_status', ['paid', 'partially_refunded', 'refunded']);
            })
            ->select(
                'title',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total) as total_revenue'),
                DB::raw('COUNT(DISTINCT order_id) as total_orders')
            )
            ->groupBy('title')
            ->orderByDesc('total_revenue')
            ->limit(100)
            ->get();

        // Customer Performance
        $customerPerformance = Order::whereBetween('processed_at', [$startDate, $endDate])
            ->whereIn('financial_status', ['paid', 'partially_refunded', 'refunded'])
            ->select(
                'customer_id',
                'email',
                DB::raw('SUM(total_price) as total_sales'),
                DB::raw('COUNT(*) as total_orders')
            )
            ->with('customer')
            ->groupBy('customer_id', 'email')
            ->orderByDesc('total_sales')
            ->limit(100)
            ->get();

        return view('reports.index', [
            'metrics' => $metrics,
            'productPerformance' => $productPerformance,
            'customerPerformance' => $customerPerformance,
            'range' => $request->get('range', '30d'),
            'dateStart' => $startDate->toDateString(),
            'dateEnd' => $endDate->toDateString(),
        ]);
    }

    protected function getDateRange(Request $request): array
    {
        $range = $request->get('range', '30d');

        if ($range === 'today') return [now()->startOfDay(), now()];
        if ($range === '7d') return [now()->subDays(6)->startOfDay(), now()];
        if ($range === '30d') return [now()->subDays(29)->startOfDay(), now()];
        if ($range === 'custom') {
            $start = $request->start ? Carbon::parse($request->start)->startOfDay() : now()->startOfMonth();
            $end = $request->end ? Carbon::parse($request->end)->endOfDay() : now();
            return [$start, $end];
        }

        return [now()->subDays(29)->startOfDay(), now()];
    }

    protected function calculateMetrics($startDate, $endDate)
    {
        // Total Transactions (Paid / Partially Refunded / Refunded)
        $revenueOrdersQuery = Order::whereBetween('processed_at', [$startDate, $endDate])
            ->whereIn('financial_status', ['paid', 'partially_refunded', 'refunded']);

        $totalTransactions = $revenueOrdersQuery->count();

        // Gross Sales & Discounts
        $grossSales = (clone $revenueOrdersQuery)->sum('subtotal_price');
        $totalDiscounts = (clone $revenueOrdersQuery)->sum('total_discounts');

        // Refunds
        $refunds = Refund::whereBetween('processed_at', [$startDate, $endDate])
            ->whereIn('order_id', $revenueOrdersQuery->pluck('id'))
            ->with('refundItems')
            ->get()
            ->sum(fn($r) => $r->refundItems->sum('subtotal'));

        // Total Revenue
        $totalRevenue = $grossSales - $totalDiscounts - $refunds;

        // Average Order Value
        $aov = $totalTransactions > 0 ? ($grossSales - $totalDiscounts) / $totalTransactions : 0;

        // Customers
        $customerIds = $revenueOrdersQuery->pluck('customer_id')->filter()->unique();
        $activeCustomerCount = $customerIds->count();
        $allTimeCustomerCount = Customer::count();

        // New Customers (created in this period)
        $newCustomerIds = Customer::whereBetween('created_at', [$startDate, $endDate])->pluck('id');
        $newCustomerCount = $newCustomerIds->count();

        // Returning Customers
        $returningCustomerIds = Customer::whereHas('orders', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('processed_at', [$startDate, $endDate]);
            })
            ->whereHas('orders', function ($q) use ($startDate) {
                $q->where('processed_at', '<', $startDate);
            })
            ->pluck('id');
        $returningCustomerCount = $returningCustomerIds->count();

        // Revenue splits
        $newCustomerRevenue = (clone $revenueOrdersQuery)
            ->whereIn('customer_id', $newCustomerIds)
            ->sum(DB::raw('subtotal_price - total_discounts'));

        $returningCustomerRevenue = (clone $revenueOrdersQuery)
            ->whereIn('customer_id', $returningCustomerIds)
            ->sum(DB::raw('subtotal_price - total_discounts'));

        // Subtract refunds for accurate revenue
        $newCustomerRefunds = Refund::whereBetween('processed_at', [$startDate, $endDate])
            ->whereIn('order_id', (clone $revenueOrdersQuery)->whereIn('customer_id', $newCustomerIds)->pluck('id'))
            ->with('refundItems')
            ->get()
            ->sum(fn($r) => $r->refundItems->sum('subtotal'));

        $returningCustomerRefunds = Refund::whereBetween('processed_at', [$startDate, $endDate])
            ->whereIn('order_id', (clone $revenueOrdersQuery)->whereIn('customer_id', $returningCustomerIds)->pluck('id'))
            ->with('refundItems')
            ->get()
            ->sum(fn($r) => $r->refundItems->sum('subtotal'));

        $newCustomerRevenue -= $newCustomerRefunds;
        $returningCustomerRevenue -= $returningCustomerRefunds;

        return [
            'total_revenue' => $totalRevenue,
            'total_transactions' => $totalTransactions,
            'aov' => $aov,
            'all_time_customers' => $allTimeCustomerCount,
            'customer_count' => $activeCustomerCount,
            'new_customer_revenue' => $newCustomerRevenue,
            'returning_customer_revenue' => $returningCustomerRevenue,
            'new_customers' => $newCustomerCount,
            'returning_customers' => $returningCustomerCount,
        ];
    }

    protected function calculateChanges($current, $previous)
    {
        $metrics = [];
        foreach ($current as $key => $value) {
            $prevValue = $previous[$key] ?? 0;
            $diff = $value - $prevValue;

            $percentage = $prevValue == 0 ? ($value > 0 ? 100 : 0) : ($diff / $prevValue) * 100;

            $metrics[$key] = [
                'value' => $value,
                'change' => round($percentage, 1)
            ];
        }
        return $metrics;
    }
}
