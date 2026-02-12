<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Calculate date range based on request
     */
    protected function getDateRange(Request $request): array
    {
        $range = $request->get('range', '30d');

        Log::info('Date range calculation', [
            'range' => $range,
            'start' => $request->start,
            'end' => $request->end
        ]);

        // Handle different date range options
        if ($range === 'today') {
            return [now()->startOfDay(), now()];
        }

        if ($range === '7d') {
            return [now()->subDays(7)->startOfDay(), now()];
        }

        if ($range === '30d') {
            return [now()->subDays(30)->startOfDay(), now()];
        }

        if ($range === 'custom') {
            return [
                Carbon::parse($request->start)->startOfDay(),
                Carbon::parse($request->end)->endOfDay()
            ];
        }

        // Default to 30 days
        return [now()->subDays(30)->startOfDay(), now()];
    }


    // ===================
    //  Dashboard page 
    // =====================
    public function index(Request $request)
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        // Calculate all metrics
        $metrics = $this->calculateMetrics($startDate, $endDate);
        $orderInsights = $this->getOrderInsights($startDate, $endDate);

        // Get customer data
        $customerData = $this->getCustomerData($startDate, $endDate);

        // Get product data
        $productData = $this->getProductData($startDate, $endDate);

        // Get sales trends
        $dailySales = $this->getDailySales($startDate, $endDate);
        $orderStatusDistribution = $this->getOrderStatusDistribution($startDate, $endDate);

        return view('dashboard.index', [
            // Metrics
            'metrics' => $metrics,
            'orderInsights' => $orderInsights,

            // Customers
            'returningCustomersList' => $customerData['returning'],
            'topCustomersBySpend' => $customerData['topSpenders'],
            'newCustomersList' => $customerData['new'],

            // Products
            'mostSoldProducts' => $productData['mostSold'],
            'highestRevenueProducts' => $productData['highestRevenue'],
            'mostRefundedProducts' => $productData['mostRefunded'],

            // Trends
            'dailySales' => $dailySales,
            'orderStatusDistribution' => $orderStatusDistribution,

            // Date info
            'dateStart' => $startDate->toDateString(),
            'dateEnd' => $endDate->toDateString(),
            'range' => $request->get('range', '30d'),
            'lastSyncTime' => Cache::get('shopify_last_sync_at'),
        ]);
    }

  private function calculateMetrics($startDate, $endDate): array
{
    // Query orders in the date range
    $ordersQuery = Order::whereBetween('processed_at', [$startDate, $endDate]);

    // Gross sales = sum of line items before discounts
    $grossSales = (clone $ordersQuery)->sum('subtotal_price');

    // Total discounts applied to orders
    $totalDiscounts = (clone $ordersQuery)->sum('total_discounts');

    // Total refunds including order adjustments
    $totalRefunds = (clone $ordersQuery)->sum('total_refunds');

    // Net sales = gross sales - discounts - total refunds
    $netSales = $grossSales - $totalDiscounts - $totalRefunds;

    // Orders count & average order value
    $orderCount = (clone $ordersQuery)->count();
    $averageOrderValue = $orderCount > 0
        ? ($grossSales - $totalDiscounts) / $orderCount
        : 0;

    return [
        'total_orders' => $orderCount,
        'total_gross_sales' => round($grossSales, 2),
        'total_discounts' => round($totalDiscounts, 2),
        'total_returns' => round($totalRefunds, 2),
        'total_net_sales' => round($netSales, 2),
        'average_order_value' => round($averageOrderValue, 2),
    ];
}



    // ===== Get order insights grouped by status ===
    private function getOrderInsights($startDate, $endDate): array
    {
        return [
            'by_financial_status' => $this->getOrdersByStatus($startDate, $endDate, 'financial_status'),
            'by_fulfillment_status' => $this->getOrdersByStatus($startDate, $endDate, 'fulfillment_status'),
            'by_is_paid' => $this->getOrdersByStatus($startDate, $endDate, 'is_paid'),
        ];
    }


    // =====  Get customer-related data =======
    private function getCustomerData($startDate, $endDate): array
    {
        return [
            'returning' => $this->getTopReturningCustomers(),
            'topSpenders' => $this->getTopCustomersBySpend($startDate, $endDate),
            'new' => $this->getNewCustomersByOrderDate($startDate, $endDate),
        ];
    }


    //=====  Get product-related data ======
    private function getProductData($startDate, $endDate): array
    {
        return [
            'mostSold' => $this->getMostSoldProducts($startDate, $endDate),
            'highestRevenue' => $this->getHighestRevenueProducts($startDate, $endDate),
            'mostRefunded' => $this->getMostRefundedProducts($startDate, $endDate),
        ];
    }


    // ======== Group orders by a specific status column ========
    private function getOrdersByStatus($startDate, $endDate, $column): array
    {
        $query = Order::whereBetween('processed_at', [$startDate, $endDate]);

        // Handle null fulfillment_status
        if ($column === 'fulfillment_status') {
            $query->selectRaw("COALESCE($column, 'unfulfilled') as status, COUNT(*) as count")
                ->groupBy('status');
        } else {
            $query->select($column, \DB::raw('COUNT(*) as count'))
                ->groupBy($column);
        }

        return $query->get()
            ->pluck('count', $column === 'fulfillment_status' ? 'status' : $column)
            ->toArray();
    }


    // ========= Get top 10 returning customers ===========
    private function getTopReturningCustomers()
    {
        return Customer::withCount('orders')
            ->having('orders_count', '>', 1)
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();
    }

    /**
     * Get customers whose first paid order falls within the selected date range.
     * "New" is defined by order processed_at (Shopify order date), not customer created_at.
     */
    private function getNewCustomersByOrderDate($startDate, $endDate)
    {
        // Customers with at least one paid/partially paid/refunded order in the period
        $customers = Customer::whereHas('orders', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('processed_at', [$startDate, $endDate])
                ->whereIn('financial_status', ['paid', 'partially_paid', 'partially_refunded', 'refunded']);
        })
            // And no paid/partially paid/refunded orders before the period start
            ->whereDoesntHave('orders', function ($query) use ($startDate) {
                $query->where('processed_at', '<', $startDate)
                    ->whereIn('financial_status', ['paid', 'partially_paid', 'partially_refunded', 'refunded']);
            })
            ->with(['orders' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('processed_at', [$startDate, $endDate])
                    ->whereIn('financial_status', ['paid', 'partially_paid', 'partially_refunded', 'refunded'])
                    ->orderBy('processed_at');
            }])
            ->get();

        // Sort by first order date in the range (most recent first) and limit to 10
        return $customers->sortByDesc(function ($customer) {
            return optional($customer->orders->first())->processed_at;
        })
            ->take(10)
            ->values();
    }


    // ======= Get top 10 customers by spend in date range ============
    private function getTopCustomersBySpend($startDate, $endDate)
    {
        return Order::whereBetween('processed_at', [$startDate, $endDate])
            ->whereIn('financial_status', ['paid', 'partially_paid', 'partially_refunded', 'refunded'])
            ->with('customer')
            ->get()
            ->groupBy('customer_id')
            ->map(function ($orders, $customerId) {
                if (!$orders->first()->customer) {
                    return null;
                }

                return (object) [
                    'customer' => $orders->first()->customer,
                    'total_spend' => $orders->sum('subtotal_price') - $orders->sum('total_discounts') - $orders->sum('total_refunds')
                ];
            })
            ->filter()
            ->sortByDesc('total_spend')
            ->take(10)
            ->values();
    }


    // ======= Get top 10 products by quantity sold =====
    private function getMostSoldProducts($startDate, $endDate): array
    {
        $orderItems = OrderItem::whereHas('order', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('processed_at', [$startDate, $endDate])
                ->whereIn('financial_status', ['paid', 'partially_paid', 'partially_refunded', 'refunded']);
        })
            ->with('product')
            ->get()
            ->groupBy('title')
            ->map(function ($items, $title) {
                return [
                    'title' => $title,
                    'total_quantity' => $items->sum('quantity'),
                    'product' => $items->first()->product
                ];
            })
            ->sortByDesc('total_quantity')
            ->take(10)
            ->values()
            ->map(function ($item) {
                return [
                    'title' => $item['title'],
                    'total_quantity' => $item['total_quantity']
                ];
            })
            ->toArray();

        return $orderItems;
    }


    // ======= Get top 10 products by revenue  ============
    private function getHighestRevenueProducts($startDate, $endDate): array
    {
        $orderItems = OrderItem::whereHas('order', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('processed_at', [$startDate, $endDate])
                ->whereIn('financial_status', ['paid', 'partially_paid', 'partially_refunded', 'refunded']);
        })
            ->with('product')
            ->get()
            ->groupBy('title')
            ->map(function ($items, $title) {
                return [
                    'title' => $title,
                    'revenue' => $items->sum('total'),
                    'product' => $items->first()->product
                ];
            })
            ->sortByDesc('revenue')
            ->take(10)
            ->values()
            ->map(function ($item) {
                return [
                    'title' => $item['title'],
                    'revenue' => (float) $item['revenue']
                ];
            })
            ->toArray();

        return $orderItems;
    }

    // ========== Get top 5 most refunded products =============
    private function getMostRefundedProducts($startDate, $endDate)
    {
        // Only get refunds for orders within our date range and valid financial statuses
        $refunds = Refund::whereHas('order', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('processed_at', [$startDate, $endDate])
                ->whereIn('financial_status', ['paid', 'partially_paid', 'partially_refunded', 'refunded']);
        })
            ->with(['refundItems.product'])
            ->get()
            ->flatMap(function ($refund) {
                return $refund->refundItems;
            })
            ->groupBy(function ($refundItem) {
                return $refundItem->product->title ?? 'Unknown Product';
            })
            ->map(function ($items, $title) {
                return [
                    'title' => $title,
                    'quantity' => $items->sum('quantity'),
                    'amount' => $items->sum('subtotal')
                ];
            })
            ->sortByDesc('amount')
            ->take(5)
            ->values()
            ->map(function ($refund) {
                return [
                    'title' => $refund['title'],
                    'quantity' => (int) $refund['quantity'],
                    'amount' => (float) $refund['amount'],
                ];
            });

        return $refunds;
    }


    // =========== Get daily sales data for the date range ===========
    private function getDailySales($startDate, $endDate): array
    {
        $orders = Order::whereBetween('processed_at', [$startDate, $endDate])
            ->whereIn('financial_status', ['paid', 'partially_paid', 'partially_refunded', 'refunded'])
            ->get();

        $ordersByDate = $orders->groupBy(function ($order) {
            return optional($order->processed_at)->format('Y-m-d');
        });

        $grossByDate = $ordersByDate->map(fn($ordersForDate) => $ordersForDate->sum('subtotal_price'));
        $discountsByDate = $ordersByDate->map(fn($ordersForDate) => $ordersForDate->sum('total_discounts'));

        $refundsByDate = Refund::whereHas('order', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('processed_at', [$startDate, $endDate])
                ->whereIn('financial_status', ['paid', 'partially_paid', 'partially_refunded', 'refunded']);
        })
            ->whereBetween('processed_at', [$startDate, $endDate])
            ->get()
            ->groupBy(function ($refund) {
                return optional($refund->processed_at)->format('Y-m-d');
            })
            ->map(function ($refundsForDate) {
                return $refundsForDate->sum('total_amount');
            });

        $dailySalesData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');

            $gross = (float) ($grossByDate[$dateKey] ?? 0);
            $discounts = (float) ($discountsByDate[$dateKey] ?? 0);
            $refunds = (float) ($refundsByDate[$dateKey] ?? 0);

            $dailySalesData[] = [
                'date' => $dateKey,
                'total' => $gross - $discounts - $refunds,
            ];

            $currentDate->addDay();
        }

        return $dailySalesData;
    }


    // ========== Get order status distribution ==========
    private function getOrderStatusDistribution($startDate, $endDate)
    {
        return Order::whereBetween('processed_at', [$startDate, $endDate])
            ->get()
            ->groupBy('financial_status')
            ->map(function ($orders, $status) {
                return [
                    'label' => $status ?? 'unknown',
                    'count' => $orders->count(),
                ];
            })
            ->values();
    }
}
