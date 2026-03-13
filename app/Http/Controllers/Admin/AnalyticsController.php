<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DistributorTarget;
use App\Models\SaleItem;
use App\Models\Sale;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analyticsService) {}

    public function index(Request $request)
    {
        $filters = $this->buildFilters($request);

        // Resolve compare year: explicit param > auto-previous FY when main FY is selected
        $compareYear = null;
        if (!empty($filters['fiscal_year'])) {
            $reqCompare  = $request->get('compare_year');
            $compareYear = ($reqCompare !== null && $reqCompare !== '')
                ? (int) $reqCompare
                : $filters['fiscal_year'] - 1;
        }

        $compareFilters      = $this->buildFiltersForYear($compareYear, $filters['month'] ?? null);
        $currentStats        = $this->analyticsService->aggregateStats($filters);
        $compareStats        = $compareFilters ? $this->analyticsService->aggregateStats($compareFilters) : null;
        $compareMonthlySales = $compareFilters ? $this->analyticsService->monthlySales($compareFilters) : [];

        return inertia('admin/analytics/Index', [
            'filters'             => $filters,
            'compareYear'         => $compareYear,
            'currentStats'        => $currentStats,
            'prevStats'           => $compareStats,
            'prevMonthlySales'    => $compareMonthlySales,
            'salesByBS'           => $this->analyticsService->salesByBS($filters),
            'salesByDistributor'  => $this->analyticsService->salesByDistributor($filters),
            'salesByProduct'      => $this->analyticsService->salesByProduct($filters),
            'monthlySales'        => $this->analyticsService->monthlySales($filters),
            'topDistributors'     => $this->analyticsService->topDistributors($filters, 5),
            'topRetailers'        => $this->analyticsService->topRetailers($filters, 5),
            'targetVsActual'      => $this->getTargetVsActual($filters),
        ]);
    }

    public function salesByRegion(Request $request)
    {
        return response()->json(
            $this->analyticsService->salesByRegion($this->buildFilters($request))
        );
    }

    public function salesByProduct(Request $request)
    {
        return response()->json(
            $this->analyticsService->salesByProduct($this->buildFilters($request))
        );
    }

    public function activityVsSales(Request $request)
    {
        return response()->json(
            $this->analyticsService->salesVsActivities($this->buildFilters($request))
        );
    }

    public function topPerformers(Request $request)
    {
        $filters = $this->buildFilters($request);
        return response()->json([
            'distributors' => $this->analyticsService->topDistributors($filters),
            'retailers'    => $this->analyticsService->topRetailers($filters),
        ]);
    }

    private function buildFilters(Request $request): array
    {
        $fiscalYear = $request->get('fiscal_year') ? (int) $request->get('fiscal_year') : null;
        $month      = $request->get('month')       ? (int) $request->get('month')       : null;

        $startDate = null;
        $endDate   = null;

        if ($fiscalYear) {
            if ($month) {
                $yearForMonth = ($month >= 4) ? $fiscalYear : $fiscalYear + 1;
                $startDate = Carbon::create($yearForMonth, $month, 1)->startOfMonth()->toDateString();
                $endDate   = Carbon::create($yearForMonth, $month, 1)->endOfMonth()->toDateString();
            } else {
                $startDate = Carbon::create($fiscalYear, 4, 1)->toDateString();
                $endDate   = Carbon::create($fiscalYear + 1, 3, 31)->toDateString();
            }
        }

        return array_filter([
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'fiscal_year' => $fiscalYear,
            'month'       => $month,
        ], fn($v) => $v !== null && $v !== '');
    }

    private function buildFiltersForYear(?int $fy, ?int $month = null): array
    {
        if (!$fy) return [];

        if ($month) {
            $yearForMonth = ($month >= 4) ? $fy : $fy + 1;
            $startDate = Carbon::create($yearForMonth, $month, 1)->startOfMonth()->toDateString();
            $endDate   = Carbon::create($yearForMonth, $month, 1)->endOfMonth()->toDateString();
        } else {
            $startDate = Carbon::create($fy, 4, 1)->toDateString();
            $endDate   = Carbon::create($fy + 1, 3, 31)->toDateString();
        }

        return ['start_date' => $startDate, 'end_date' => $endDate, 'fiscal_year' => $fy];
    }

    private function getTargetVsActual(array $filters): array
    {
        $fy = $filters['fiscal_year'] ?? null;
        if (!$fy) return [];

        $startDate = Carbon::create($fy, 4, 1)->toDateString();
        $endDate   = Carbon::create($fy + 1, 3, 31)->toDateString();

        $targets = DistributorTarget::query()
            ->join('products', 'distributor_targets.product_id', '=', 'products.id')
            ->where('distributor_targets.fiscal_year', $fy)
            ->groupBy('distributor_targets.product_id', 'products.name', 'products.uom_1')
            ->select(
                'distributor_targets.product_id',
                'products.name as product_name',
                'products.uom_1 as uom',
                DB::raw('SUM(distributor_targets.target_qty) as total_target')
            )
            ->get()
            ->keyBy('product_id');

        if ($targets->isEmpty()) return [];

        $actuals = SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.sale_type', Sale::Type_Distributor)
            ->whereBetween('sales.date', [$startDate, $endDate])
            ->whereIn('sale_items.product_id', $targets->keys())
            ->groupBy('sale_items.product_id')
            ->select('sale_items.product_id', DB::raw('SUM(sale_items.quantity) as total_actual'))
            ->get()
            ->keyBy('product_id');

        return $targets->map(function ($t) use ($actuals) {
            $targetQty = (float) $t->total_target;
            $actualQty = (float) ($actuals->get($t->product_id)?->total_actual ?? 0);
            return [
                'product_name' => $t->product_name,
                'uom'          => $t->uom,
                'total_target' => $targetQty,
                'total_actual' => $actualQty,
                'achievement'  => $targetQty > 0 ? round($actualQty / $targetQty * 100, 1) : null,
            ];
        })->sortByDesc('total_target')->values()->all();
    }
}
