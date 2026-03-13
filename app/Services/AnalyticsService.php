<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function salesByRegion(array $filters = []): \Illuminate\Support\Collection
    {
        $q = Sale::query()
            ->join('provinces', 'sales.province_id', '=', 'provinces.id')
            ->select('provinces.id', 'provinces.name as province_name',
                DB::raw('SUM(sales.total_amount) as total_sales'),
                DB::raw('COUNT(sales.id) as transaction_count'))
            ->groupBy('provinces.id', 'provinces.name');

        $this->applyDateFilter($q, $filters);

        return $q->orderByDesc('total_sales')->get();
    }

    public function salesByProduct(array $filters = []): \Illuminate\Support\Collection
    {
        $q = SaleItem::query()
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->select('products.id', 'products.name as product_name',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.subtotal) as total_sales'))
            ->groupBy('products.id', 'products.name');

        $this->applyDateFilter($q, $filters, 'sales');

        return $q->orderByDesc('total_sales')->get();
    }

    public function salesByBS(array $filters = []): \Illuminate\Support\Collection
    {
        $q = Sale::query()
            ->where('sale_type', Sale::Type_Retailer)
            ->join('users', 'sales.created_by_uid', '=', 'users.id')
            ->select('users.id', 'users.name as user_name',
                DB::raw('SUM(sales.total_amount) as total_sales'),
                DB::raw('COUNT(sales.id) as transaction_count'))
            ->groupBy('users.id', 'users.name');

        $this->applyDateFilter($q, $filters);

        return $q->orderByDesc('total_sales')->get();
    }

    public function salesByDistributor(array $filters = []): \Illuminate\Support\Collection
    {
        $q = Sale::query()
            ->where('sale_type', Sale::Type_Distributor)
            ->join('customers', 'sales.distributor_id', '=', 'customers.id')
            ->select('customers.id', 'customers.name as distributor_name',
                DB::raw('SUM(sales.total_amount) as total_sales'),
                DB::raw('COUNT(sales.id) as transaction_count'))
            ->groupBy('customers.id', 'customers.name');

        $this->applyDateFilter($q, $filters);

        return $q->orderByDesc('total_sales')->get();
    }

    public function topDistributors(array $filters = [], int $limit = 10): \Illuminate\Support\Collection
    {
        $q = Sale::query()
            ->join('customers', 'sales.distributor_id', '=', 'customers.id')
            ->select('customers.id', 'customers.name',
                DB::raw('SUM(sales.total_amount) as total_sales'),
                DB::raw('COUNT(sales.id) as transaction_count'))
            ->groupBy('customers.id', 'customers.name');

        $this->applyDateFilter($q, $filters);

        return $q->orderByDesc('total_sales')->limit($limit)->get();
    }

    public function topRetailers(array $filters = [], int $limit = 10): \Illuminate\Support\Collection
    {
        $q = Sale::query()
            ->join('customers', 'sales.retailer_id', '=', 'customers.id')
            ->whereNotNull('sales.retailer_id')
            ->select('customers.id', 'customers.name',
                DB::raw('SUM(sales.total_amount) as total_sales'),
                DB::raw('COUNT(sales.id) as transaction_count'))
            ->groupBy('customers.id', 'customers.name');

        $this->applyDateFilter($q, $filters);

        return $q->orderByDesc('total_sales')->limit($limit)->get();
    }

    public function monthlySales(array $filters = []): \Illuminate\Support\Collection
    {
        $q = Sale::query()
            ->select(
                DB::raw("DATE_FORMAT(date, '%Y-%m') as month"),
                DB::raw('SUM(total_amount) as total_sales'),
                DB::raw('COUNT(id) as transaction_count'))
            ->groupBy('month');

        $this->applyDateFilter($q, $filters);

        return $q->orderBy('month')->get();
    }

    /**
     * Aggregate key stats: total sales, transactions, active distributors, and qty sold.
     * Used for YoY comparison on the analytics dashboard.
     */
    public function aggregateStats(array $filters = []): array
    {
        $q = Sale::query();
        $this->applyDateFilter($q, $filters);

        $totalSales   = (clone $q)->sum('total_amount');
        $transactions = (clone $q)->count();
        $distributors = (clone $q)
            ->where('sale_type', Sale::Type_Distributor)
            ->whereNotNull('distributor_id')
            ->distinct()
            ->count('distributor_id');

        $qtyQ = SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id');
        $this->applyDateFilter($qtyQ, $filters, 'sales');
        $totalQty = $qtyQ->sum('sale_items.quantity');

        return [
            'total_sales'         => (float) $totalSales,
            'total_transactions'  => (int)   $transactions,
            'active_distributors' => (int)   $distributors,
            'total_qty'           => (float) $totalQty,
        ];
    }

    public function salesVsActivities(array $filters = []): array
    {
        $provinceId = $filters['province_id'] ?? null;

        // Sales per province
        $salesQuery = Sale::query()
            ->join('provinces', 'sales.province_id', '=', 'provinces.id')
            ->select('provinces.id', 'provinces.name as province_name',
                DB::raw('SUM(sales.total_amount) as total_sales'))
            ->whereNotNull('sales.province_id')
            ->groupBy('provinces.id', 'provinces.name');

        $this->applyDateFilter($salesQuery, $filters);

        if ($provinceId) {
            $salesQuery->where('sales.province_id', $provinceId);
        }

        $sales = $salesQuery->get()->keyBy('id');

        // Activities per province: via activity.user_id → customers.assigned_user_id → province
        // COUNT(DISTINCT) ensures each activity counted once per province
        $activitiesQuery = Activity::query()
            ->join('customers', 'customers.assigned_user_id', '=', 'activities.user_id')
            ->join('provinces', 'customers.province_id', '=', 'provinces.id')
            ->select('provinces.id', 'provinces.name as province_name',
                DB::raw('COUNT(DISTINCT activities.id) as activity_count'))
            ->whereNotNull('customers.province_id')
            ->groupBy('provinces.id', 'provinces.name');

        $this->applyDateFilter($activitiesQuery, $filters, 'activities', 'date');

        if ($provinceId) {
            $activitiesQuery->where('customers.province_id', $provinceId);
        }

        $activities = $activitiesQuery->get()->keyBy('id');

        // Merge
        $allProvinceIds = $sales->keys()->merge($activities->keys())->unique();

        return $allProvinceIds->map(function ($id) use ($sales, $activities) {
            return [
                'province_id'    => $id,
                'province_name'  => $sales->get($id)?->province_name ?? $activities->get($id)?->province_name,
                'total_sales'    => (float) ($sales->get($id)?->total_sales ?? 0),
                'activity_count' => (int) ($activities->get($id)?->activity_count ?? 0),
            ];
        })->values()->all();
    }

    private function applyDateFilter($query, array $filters, string $table = 'sales', string $column = 'date'): void
    {
        if (!empty($filters['start_date'])) {
            $query->whereDate("{$table}.{$column}", '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate("{$table}.{$column}", '<=', $filters['end_date']);
        }
        if (!empty($filters['year'])) {
            $query->whereYear("{$table}.{$column}", $filters['year']);
        }
        if (!empty($filters['month'])) {
            $query->whereMonth("{$table}.{$column}", $filters['month']);
        }
    }
}
