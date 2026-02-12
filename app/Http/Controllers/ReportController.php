<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        [$startDate, $endDate] = $this->getDateRange($request);
        
        // Calculate metrics for current period
        $currentMetrics = $this->calculateMetrics($startDate, $endDate);
        
        // Calculate metrics for previous year (same period)
        $lastYearStartDate = $startDate->copy()->subYear();
        $lastYearEndDate = $endDate->copy()->subYear();
        $lastYearMetrics = $this->calculateMetrics($lastYearStartDate, $lastYearEndDate);

        // Calculate percentage changes
        $metrics = $this->calculateChanges($currentMetrics, $lastYearMetrics);

        // Product Performance: Revenue, Orders, Quantity
        $productPerformance = OrderItem::whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('processed_at', [$startDate, $endDate])
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

        // Customer Performance: Sales, Orders
        $customerPerformance = Order::whereBetween('processed_at', [$startDate, $endDate])
            ->whereIn('financial_status', ['paid', 'partially_refunded', 'refunded'])
            ->select(
                'customer_id',
                'email',
                DB::raw('SUM(total_price) as total_sales'),
                DB::raw('COUNT(*) as total_orders')
            )
            ->groupBy('customer_id', 'email')
            ->with('customer')
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
        if ($range === '7d') return [now()->subDays(7)->startOfDay(), now()];
        if ($range === '30d') return [now()->subDays(30)->startOfDay(), now()];
        if ($range === 'custom') {
            return [
                \Carbon\Carbon::parse($request->start)->startOfDay(),
                \Carbon\Carbon::parse($request->end)->endOfDay()
            ];
        }
        
        return [now()->subDays(30)->startOfDay(), now()];
    }

    protected function calculateMetrics($startDate, $endDate)
    {
        // Total Orders (All orders regardless of status, matching Dashboard)
        $allOrders = Order::whereBetween('processed_at', [$startDate, $endDate]);
        $totalOrders = $allOrders->count();

        // Revenue Orders (Paid/Refunded)
        $revenueOrders = Order::whereBetween('processed_at', [$startDate, $endDate])
            ->whereIn('financial_status', ['paid', 'partially_paid', 'partially_refunded', 'refunded'])
            ->with(['customer', 'refunds.refundItems'])
            ->get();
        
        $grossSales = $revenueOrders->sum('subtotal_price');
        $discounts = $revenueOrders->sum('total_discounts');
        $refunds = Refund::whereBetween('processed_at', [$startDate, $endDate])
            ->with('refundItems')
            ->get()
            ->sum(function ($refund) {
                return $refund->refundItems->sum('subtotal');
            });
        
        $totalRevenue = $grossSales - $discounts - $refunds;
        
        // Customers
        $customerCount = $revenueOrders->pluck('customer_id')->unique()->filter()->count();
        $allTimeCustomerCount = \App\Models\Customer::count(); 
        
        // New Customers (Created in period)
        $newCustomersCount = \App\Models\Customer::whereBetween('created_at', [$startDate, $endDate])->count();

        // Returning Customers (Active in period + >1 all-time order)
        // Note: This logic matches DashboardController's getReturningCustomersCount
        $returningCustomersCount = \App\Models\Customer::whereHas('orders', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('processed_at', [$startDate, $endDate]);
            })
            ->withCount('orders')
            ->having('orders_count', '>', 1)
            ->count();


        // Revenue Splits (Approximation using user creation date for segmenting revenue)
        // We stick to the simple logic for Revenue: New = Customer created in range. Returning = Customer created before range.
        $newCustomerOrders = $revenueOrders->filter(function ($order) use ($startDate, $endDate) {
            return $order->customer && $order->customer->created_at->between($startDate, $endDate);
        });

        $returningCustomerOrders = $revenueOrders->filter(function ($order) use ($startDate, $endDate) {
            return $order->customer && $order->customer->created_at->lt($startDate);
        });

        $newCustomerRefunds = $this->calculateRefundsForOrders($newCustomerOrders, $startDate, $endDate);
        $newCustomerRevenue = $newCustomerOrders->sum('subtotal_price') 
                              - $newCustomerOrders->sum('total_discounts') 
                              - $newCustomerRefunds;
        
        $returningCustomerRefunds = $this->calculateRefundsForOrders($returningCustomerOrders, $startDate, $endDate);
        $returningCustomerRevenue = $returningCustomerOrders->sum('subtotal_price') 
                                    - $returningCustomerOrders->sum('total_discounts') 
                                    - $returningCustomerRefunds;

        return [
            'total_revenue' => $totalRevenue,
            'total_transactions' => $totalOrders, // Using All Orders count
            'aov' => $revenueOrders->count() > 0 ? ($grossSales - $discounts) / $revenueOrders->count() : 0, // AOV typically based on Paid Orders
            'all_time_customers' => $allTimeCustomerCount,
            'customer_count' => $customerCount,
            'new_customer_revenue' => $newCustomerRevenue,
            'returning_customer_revenue' => $returningCustomerRevenue,
            'new_customers' => $newCustomersCount,
            'returning_customers' => $returningCustomersCount,
        ];
    }

    /**
     * Calculate total refund amount for a given set of orders within a date range.
     */
    protected function calculateRefundsForOrders($orders, $startDate, $endDate): float
    {
        $orderIds = $orders->pluck('id')->filter()->values();

        if ($orderIds->isEmpty()) {
            return 0.0;
        }

        return Refund::whereBetween('processed_at', [$startDate, $endDate])
            ->whereIn('order_id', $orderIds)
            ->with('refundItems')
            ->get()
            ->sum(function ($refund) {
                return $refund->refundItems->sum('subtotal');
            });
    }

    protected function calculateChanges($current, $previous)
    {
        $metrics = [];
        foreach ($current as $key => $value) {
            $prevValue = $previous[$key] ?? 0;
            $diff = $value - $prevValue;
            
            // Avoid division by zero
            if ($prevValue == 0) {
                $percentage = $value > 0 ? 100 : 0;
            } else {
                $percentage = ($diff / $prevValue) * 100;
            }
            
            $metrics[$key] = [
                'value' => $value,
                'change' => round($percentage, 1)
            ];
        }
        return $metrics;
    }
}
