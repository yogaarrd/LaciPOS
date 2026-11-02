<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->startOfDay();

        $baseQuery = Order::where('status', 'completed')
            ->where('created_at', '>=', $today);

        $summary = (clone $baseQuery)->selectRaw('SUM(total_amount) as revenue, SUM(tax_amount) as tax, SUM(service_charge_amount) as service_charge, COUNT(*) as transactions')->first();
        $totalRevenue = (float) $summary->revenue;
        $totalTax = (float) $summary->tax;
        $totalServiceCharge = (float) $summary->service_charge;
        $netRevenue = $totalRevenue - $totalTax - $totalServiceCharge;
        $totalTransactions = (int) $summary->transactions;

        $activeCashiers = User::where('role', 'cashier')->where('is_active', true)->count();

        $totalCogs = OrderItem::whereHas('order', fn ($q) =>
            $q->where('status', 'completed')->where('created_at', '>=', $today)
        )->sum(DB::raw('snapshot_cogs * quantity'));

        $grossProfit = $netRevenue - $totalCogs;

        $restockProducts = Product::active()
            ->lowStock()
            ->with('category')
            ->orderBy('stock')
            ->limit(10)
            ->get(['id', 'name', 'stock', 'min_stock_alert', 'category_id']);

        $recentTransactions = Order::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(7)
            ->get();

        $dailySales = Order::where('status', 'completed')
            ->where('created_at', '>=', $today->copy()->subDays(6))
            ->where('created_at', '<', $today->copy()->addDay())
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue, SUM(tax_amount) as tax, SUM(service_charge_amount) as service_charge')
            ->groupByRaw('DATE(created_at)')->get()->keyBy('date');

        $salesData = collect(range(6, 0))->map(function ($daysAgo) use ($today, $dailySales) {
            $date = $today->copy()->subDays($daysAgo);
            $day = $dailySales->get($date->toDateString());
            $dayTotal = (float) ($day?->revenue ?? 0);
            $dayTax = (float) ($day?->tax ?? 0);
            $daySc = (float) ($day?->service_charge ?? 0);
            return [
                'date' => $date->format('d/m'),
                'full_date' => $date->toDateString(),
                'day' => $date->locale('id')->translatedFormat('D'),
                'revenue' => $dayTotal - $dayTax - $daySc,
            ];
        });

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'totalRevenue' => $netRevenue,
                'totalTransactions' => $totalTransactions,
                'grossProfit' => $grossProfit,
                'activeCashiers' => $activeCashiers,
            ],
            'restockProducts' => $restockProducts,
            'salesData' => $salesData,
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
