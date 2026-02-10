<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /* ---------------- DATE RANGE ---------------- */

    protected function getDateRange(Request $request): array
    {
        $range = $request->get('range', '30d');

        return match ($range) {
            'today' => [now()->startOfDay(), now()],
            '7d'    => [now()->subDays(7)->startOfDay(), now()],
            '30d'   => [now()->subDays(30)->startOfDay(), now()],
            'custom' => [
                Carbon::parse($request->start)->startOfDay(),
                Carbon::parse($request->end)->endOfDay()
            ],
            default => [now()->subDays(30)->startOfDay(), now()],
        };
    }

    /* ---------------- DASHBOARD PAGE ---------------- */

    public function index(Request $request)
    {
        [$start, $end] = $this->getDateRange($request);

        $orders = Order::whereBetween('created_at', [$start, $end]);
$paidOrders = Order::whereBetween('created_at', [$start, $end])
    ->where('is_paid', true);

$totalGrossSales = (float) $paidOrders->sum('total_price');
$totalDiscounts  = (float) $paidOrders->sum('total_discounts');
$totalReturns    = (float) $paidOrders->sum('total_refunds');   
$netSales        = $totalGrossSales - $totalDiscounts - $totalReturns;

$metrics = [
    'total_orders' => $orders->count(),
    'total_gross_sales' => round($totalGrossSales, 2),
    'discounts' => round($totalDiscounts, 2),
    'returns' => round($totalReturns, 2),
    'net_sales' => round($netSales, 2),
    'average_order_value' => round($paidOrders->avg('total_price'), 2),
    'new_customers' => Customer::whereBetween('created_at', [$start, $end])->count(),
    'returning_customers' => Customer::whereHas('orders', function ($q) use ($start, $end) {
        $q->whereBetween('created_at', [$start, $end]);
    })->withCount('orders')->having('orders_count', '>', 1)->count(),
];


        /* -------- ORDER INSIGHTS -------- */

        $orderInsights = [
            'by_financial_status' => $this->groupOrders($start, $end, 'financial_status'),
            'by_fulfillment_status' => $this->groupOrders($start, $end, 'fulfillment_status'),
            'by_is_paid' => $this->groupOrders($start, $end, 'is_paid'),
        ];

        /* -------- CUSTOMER LISTS -------- */

        $returningCustomersList = Customer::withCount('orders')
            ->having('orders_count', '>', 1)
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();

        $topCustomersBySpend = Order::whereBetween('created_at', [$start, $end])
            ->where('is_paid', true)
            ->select('customer_id', DB::raw('SUM(total_price) as total_spend'))
            ->groupBy('customer_id')
            ->orderByDesc('total_spend')
            ->with('customer')
            ->limit(10)
            ->get();

        $newCustomersList = Customer::latest()->limit(10)->get();

        /* -------- PRODUCT INSIGHTS -------- */

        $mostSoldProducts = $this->productStats($start, $end, 'quantity');
        $highestRevenueProducts = $this->productStats($start, $end, 'total', true);

        /* -------- DAILY SALES -------- */

        $dailySales = $this->dailySales($start, $end);

        /* -------- STATUS DISTRIBUTION -------- */

        $orderStatusDistribution = Order::whereBetween('created_at', [$start, $end])
            ->select('financial_status', DB::raw('count(*) as count'))
            ->groupBy('financial_status')
            ->get()
            ->map(fn($row) => [
                'label' => $row->financial_status ?? 'unknown',
                'count' => (int) $row->count
            ]);

        return view('dashboard.index', [
            'metrics' => $metrics,
            'orderInsights' => $orderInsights,
            'returningCustomersList' => $returningCustomersList,
            'topCustomersBySpend' => $topCustomersBySpend,
            'newCustomersList' => $newCustomersList,
            'mostSoldProducts' => $mostSoldProducts,
            'highestRevenueProducts' => $highestRevenueProducts,
            'dailySales' => $dailySales,
            'orderStatusDistribution' => $orderStatusDistribution,
            'dateStart' => $start->toDateString(),
            'dateEnd' => $end->toDateString(),
            'range' => $request->get('range', '30d'),
            'lastSyncTime' => Cache::get('shopify_last_sync_at'),
        ]);
    }

    /* ---------------- HELPERS ---------------- */

    private function groupOrders($start, $end, $column)
    {
        $select = $column === 'fulfillment_status'
            ? DB::raw("COALESCE($column, 'unfulfilled') as $column")
            : $column;

        return Order::whereBetween('created_at', [$start, $end])
            ->select($select, DB::raw('count(*) as count'))
            ->groupBy($column)
            ->pluck('count', $column)
            ->toArray();
    }

    private function dailySales($start, $end)
    {
        $rows = Order::whereBetween('created_at', [$start, $end])
            ->where('is_paid', true)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_price) as total'))
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $data = [];
        for ($date = $start->copy(); $date <= $end; $date->addDay()) {
            $key = $date->format('Y-m-d');
            $data[] = ['date' => $key, 'total' => (float) ($rows[$key]->total ?? 0)];
        }

        return $data;
    }

private function productStats($start, $end, $field, $paidOnly = false)
{
    $query = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
        ->whereBetween('orders.created_at', [$start, $end]);

    if ($paidOnly) {
        $query->where('orders.is_paid', true);
    }

    $products = $query->select(
        'order_items.title',
        DB::raw("SUM(order_items.$field) as value")
    )
    ->groupBy('order_items.title')
    ->orderByDesc('value')
    ->limit(10)
    ->get();

    // Map keys properly based on what kind of field it is
    if ($field === 'quantity') {
        return $products->map(fn($p) => [
            'title' => $p->title,
            'total_quantity' => (int) $p->value,
        ])->toArray();
    } else { // 'total' revenue
        return $products->map(fn($p) => [
            'title' => $p->title,
            'revenue' => (float) $p->value,
        ])->toArray();
    }
}

}
