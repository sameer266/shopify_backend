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


// ======================
//   Main report view`
// ======================

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

        // Chart Data
        $chartData = $this->getChartData($startDate, $endDate);

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
          
            ->get();

        return view('reports.index', [
            'metrics' => $metrics,
            'chartData' => $chartData,
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

    protected function getChartData($startDate, $endDate)
    {
        $daysDiff = $startDate->diffInDays($endDate);
        
        //  grouping based on date range
        if ($daysDiff <= 7) {
            $groupBy = 'day';
            $format = '%Y-%m-%d';
        } elseif ($daysDiff <= 31) {
            $groupBy = 'day';
            $format = '%Y-%m-%d';
        } else {
            $groupBy = 'week';
            $format = '%Y-%U';
        }

        // Get orders grouped by period
        $ordersData = Order::whereBetween('processed_at', [$startDate, $endDate])
            ->whereIn('financial_status', ['paid', 'partially_refunded', 'refunded'])
            ->select(
                DB::raw("DATE_FORMAT(processed_at, '{$format}') as period"),
                DB::raw('SUM(subtotal_price - total_discounts) as revenue'),
                DB::raw('COUNT(*) as order_count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Get refunds grouped by period
        $refundsData = Refund::whereBetween('processed_at', [$startDate, $endDate])
            ->select(
                DB::raw("DATE_FORMAT(processed_at, '{$format}') as period"),
                DB::raw('SUM(total_amount) as refund_amount')
            )
            ->groupBy('period')
            ->get()
            ->pluck('refund_amount', 'period');

        $labels = [];
        $revenueData = [];
        $orderCountData = [];

        foreach ($ordersData as $data) {
            $period = $data->period;
            

            if ($groupBy === 'day') {
                $labelDate = Carbon::createFromFormat('Y-m-d', $period);
                $label = $labelDate->format('M j');
            } else {
                $parts = explode('-', $period);
                $year = $parts[0];
                $week = $parts[1];
                $label = "Week {$week}";
            }

            $labels[] = $label;
            $refundAmount = $refundsData->get($period, 0);
            $revenueData[] = round($data->revenue - $refundAmount, 2);
            $orderCountData[] = $data->order_count;
        }

        return [
            'labels' => $labels,
            'revenue' => $revenueData,
            'orders' => $orderCountData,
        ];
    }


    // ======================
    //   Metrics calculation
    // ======================
protected function calculateMetrics($startDate, $endDate)
{
    // Total Transactions (monetary value instead of count)
    $revenueOrdersQuery = Order::whereBetween('processed_at', [$startDate, $endDate])
        ->whereIn('financial_status', ['paid', 'partially_refunded', 'refunded']);


    $totalTransactionsAmount = (clone $revenueOrdersQuery)
        ->sum(DB::raw('total_price')); 

    // Gross Sales & Discounts
    $grossSales = (clone $revenueOrdersQuery)->sum('subtotal_price');
    $totalDiscounts = (clone $revenueOrdersQuery)->sum('total_discounts');

    // Refunds
    $refunds = Refund::whereBetween('processed_at', [$startDate, $endDate])
        ->whereIn('order_id', $revenueOrdersQuery->pluck('id'))
        ->with('refundItems', 'orderAdjustments')
        ->get()
        ->sum(function ($r) {
            $refundSubtotal = $r->refundItems->sum('subtotal');
            $adjustmentAmount = $r->orderAdjustments->sum('amount');
            $adjustmentTax = $r->orderAdjustments->sum('tax_amount');
            return $refundSubtotal + $adjustmentAmount + $adjustmentTax;
        });

    // Total Revenue (after discounts & refunds)
    $totalRevenue = $grossSales - $totalDiscounts - $refunds;

    // Average Order Value
    $totalTransactionsCount = $revenueOrdersQuery->count();
    $aov = $totalTransactionsCount > 0 ? ($grossSales - $totalDiscounts) / $totalTransactionsCount : 0;

    // Customers
    $customerIds = $revenueOrdersQuery->pluck('customer_id')->filter()->unique();
    $activeCustomerCount = $customerIds->count();

    // All-time customer count
    $allTimeCustomerCount = Customer::count();

    // New Customers in this period (based on Shopify signup date)
    $newCustomerIds = Customer::whereBetween('shopify_created_at', [$startDate, $endDate])->pluck('id');
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

    // New Customer Revenue (after refunds)
    $newCustomerRevenue = (clone $revenueOrdersQuery)
        ->whereIn('customer_id', $newCustomerIds)
        ->sum(DB::raw('subtotal_price - total_discounts'));

    $newCustomerRefunds = Refund::whereBetween('processed_at', [$startDate, $endDate])
        ->whereIn('order_id', (clone $revenueOrdersQuery)->whereIn('customer_id', $newCustomerIds)->pluck('id'))
        ->with('refundItems', 'orderAdjustments')
        ->get()
        ->sum(function ($r) {
            $refundSubtotal = $r->refundItems->sum('subtotal');
            $adjustmentAmount = $r->orderAdjustments->sum('amount');
            $adjustmentTax = $r->orderAdjustments->sum('tax_amount');
            return $refundSubtotal + $adjustmentAmount + $adjustmentTax;
        });

    $newCustomerRevenue -= $newCustomerRefunds;

    // Returning Customer Revenue (after refunds)
    $returningCustomerRevenue = (clone $revenueOrdersQuery)
        ->whereIn('customer_id', $returningCustomerIds)
        ->sum(DB::raw('subtotal_price - total_discounts'));

    $returningCustomerRefunds = Refund::whereBetween('processed_at', [$startDate, $endDate])
        ->whereIn('order_id', (clone $revenueOrdersQuery)->whereIn('customer_id', $returningCustomerIds)->pluck('id'))
        ->with('refundItems', 'orderAdjustments')
        ->get()
        ->sum(function ($r) {
            $refundSubtotal = $r->refundItems->sum('subtotal');
            $adjustmentAmount = $r->orderAdjustments->sum('amount');
            $adjustmentTax = $r->orderAdjustments->sum('tax_amount');
            return $refundSubtotal + $adjustmentAmount + $adjustmentTax;
        });

    $returningCustomerRevenue -= $returningCustomerRefunds;

    return [
        'total_revenue' => $totalRevenue,
        'total_transactions_amount' => $totalTransactionsAmount, // NEW: monetary total
        'total_transactions_count' => $totalTransactionsCount,   // still keeping count
        'aov' => $aov,
        'all_time_customers' => $allTimeCustomerCount,          // NEW: all-time customers
        'customer_count' => $activeCustomerCount,
        'new_customer_revenue' => $newCustomerRevenue,          // NEW: in NPR
        'returning_customer_revenue' => $returningCustomerRevenue, // NEW: in NPR
        'new_customers' => $newCustomerCount,
        'returning_customers' => $returningCustomerCount,
    ];
}



// ======================
//   Percentage change calculation
// ======================
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
