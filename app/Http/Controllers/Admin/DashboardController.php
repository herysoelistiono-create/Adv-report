<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityPlan;
use App\Models\ActivityTarget;
use App\Models\ActivityType;
use App\Models\Sale;
use App\Models\DistributorStock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function bsDashboard(Request $request)
    {
        // TODO: refactor karena kode dupikat dengan ActivityTargetController@index()
        $user = auth()->user(); // atau Auth::user()
        $nowMonth = now()->month;
        $defaultYear = $nowMonth >= 4 ? now()->year : now()->year - 1;

        $year = (int) $request->get('year', $defaultYear);
        $month = (int) $request->get('month', $nowMonth);
        $quarter = null;

        if (!$quarter) {
            if (in_array($month, [4, 5, 6])) $quarter = 1;
            elseif (in_array($month, [7, 8, 9])) $quarter = 2;
            elseif (in_array($month, [10, 11, 12])) $quarter = 3;
            else $quarter = 4;
        }

        $fiscalQuarterMonths = [
            1 => [4, 5, 6],
            2 => [7, 8, 9],
            3 => [10, 11, 12],
            4 => [1, 2, 3],
        ];

        $months = $fiscalQuarterMonths[$quarter];
        $startYear = ($quarter == 4) ? $year + 1 : $year;

        $start = Carbon::createFromDate($startYear, $months[0], 1)->startOfDay();
        $end = Carbon::createFromDate($startYear, $months[2], 1)->endOfMonth()->endOfDay();

        $targets = ActivityTarget::with([
            'user:id,username,name',
            'details',
            'details.type:id,name',
        ])->where('user_id', $user->id)
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->get();

        // Ambil data rencana kegiatan (plans)
        $plans = ActivityPlan::with('details')
            ->where('user_id', $user->id)
            ->where('status', ActivityPlan::Status_Approved)
            ->whereBetween('date', [$start, $end])
            ->get();

        $plan_details_by_type_ids = [];

        foreach ($plans as $plan) {
            $planMonth = Carbon::parse($plan->date)->month;
            $monthIndex = array_search($planMonth, $months);

            if ($monthIndex === false) continue;

            foreach ($plan->details as $detail) {
                $typeId = $detail->type_id;

                if (!isset($plan_details_by_type_ids[$typeId])) {
                    $plan_details_by_type_ids[$typeId] = [
                        'quarter_qty' => 0,
                        'month1_qty' => 0,
                        'month2_qty' => 0,
                        'month3_qty' => 0,
                    ];
                }

                $plan_details_by_type_ids[$typeId]['quarter_qty'] += 1;
                $monthKey = 'month' . ($monthIndex + 1) . '_qty';
                $plan_details_by_type_ids[$typeId][$monthKey] += 1;
            }
        }

        // Ambil data realisasi kegiatan (activities)
        $activities = Activity::query()
            ->where('user_id', $user->id)
            ->where('status', Activity::Status_Approved)
            ->whereBetween('date', [$start, $end])
            ->get();

        $actvities_by_type_ids = [];

        foreach ($activities as $activity) {
            $activityMonth = Carbon::parse($activity->date)->month;
            $monthIndex = array_search($activityMonth, $months);

            if ($monthIndex === false) continue;

            $typeId = $activity->type_id;

            if (!isset($actvities_by_type_ids[$typeId])) {
                $actvities_by_type_ids[$typeId] = [
                    'quarter_qty' => 0,
                    'month1_qty' => 0,
                    'month2_qty' => 0,
                    'month3_qty' => 0,
                ];
            }

            $actvities_by_type_ids[$typeId]['quarter_qty'] += 1;
            $monthKey = 'month' . ($monthIndex + 1) . '_qty';
            $actvities_by_type_ids[$typeId][$monthKey] += 1;
        }
        
        return inertia('admin/dashboard/Index', [
            'data' => [
                'types' => ActivityType::where('active', true)
                    ->select(['id', 'name', 'weight'])
                    ->orderBy('name', 'asc')
                    ->get(),
                'targets' => count($targets) > 0 ? $targets[0]->details : [],
                'plans' => $plan_details_by_type_ids,
                'activities' => $actvities_by_type_ids,
            ],
            ''
        ]);
    }

    public function index(Request $request)
    {
        $role = $request->user()->role;

        if ($role === User::Role_BS || $role === User::Role_FieldOfficer) {
            return $this->bsDashboard($request);
        }

        if ($role === User::Role_Agronomist || $role === User::Role_ASM) {
            return $this->agronomistDashboard($request);
        }

        if ($role === User::Role_Manager || $role === User::Role_Admin) {
            return $this->managerDashboard($request);
        }

        if ($role === User::Role_Distributor) {
            return $this->distributorDashboard($request);
        }

        // Fallback
        return inertia('admin/dashboard/Index', [
            'data' => [],
            'period' => ['label' => 'This Month', 'start_date' => '', 'end_date' => ''],
        ]);
    }

    private function agronomistDashboard(Request $request)
    {
        $user = auth()->user();
        $nowMonth    = now()->month;
        $defaultYear = in_array($nowMonth, [1, 2, 3]) ? now()->year - 1 : now()->year;
        $year     = (int) $request->get('year', $defaultYear);
        $month    = (int) $request->get('month', $nowMonth);
        $viewType = $request->get('view_type', 'month');
        $quarter  = (int) $request->get('quarter', $this->getQuarterFromMonth($month));

        $fiscalQuarterMonths = [
            1 => [4, 5, 6],
            2 => [7, 8, 9],
            3 => [10, 11, 12],
            4 => [1, 2, 3],
        ];

        $bsUsers = User::where('parent_id', $user->id)
            ->where('role', User::Role_BS)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $bsUserIds = $bsUsers->pluck('id')->toArray();

        $activityTypes = ActivityType::where('active', true)->orderBy('name')->get(['id', 'name', 'weight']);
        $typeIds = $activityTypes->pluck('id')->toArray();

        if ($viewType === 'month') {
            // Fiscal year fix: Jan-Mar belong to fiscal_year+1 as calendar year
            $calYear = in_array($month, [1, 2, 3]) ? $year + 1 : $year;
            $startOfMonth = Carbon::createFromDate($calYear, $month, 1)->startOfDay();
            $endOfMonth   = $startOfMonth->copy()->endOfMonth();

            $activities = Activity::whereIn('user_id', $bsUserIds)
                ->where('status', Activity::Status_Approved)
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->get(['user_id', 'date', 'type_id']);

            $monthLabel    = $startOfMonth->translatedFormat('M Y');
            $columns       = [$monthLabel];
            $colTypeTotals = [array_fill_keys($typeIds, 0)];
            $columnTotals  = [0];
            $grandTotal    = 0;
            $rows          = [];

            foreach ($bsUsers as $bs) {
                $userActs   = $activities->where('user_id', $bs->id);
                $typeCounts = [];
                foreach ($typeIds as $tid) {
                    $count = $userActs->where('type_id', $tid)->count();
                    $typeCounts[$tid] = $count;
                    $colTypeTotals[0][$tid] += $count;
                }
                $monthTotal       = $userActs->count();
                $columnTotals[0] += $monthTotal;
                $rows[]           = ['name' => $bs->name, 'data' => [['type_counts' => $typeCounts, 'total' => $monthTotal]], 'total' => $monthTotal];
                $grandTotal      += $monthTotal;
            }

            $periodLabel = $startOfMonth->translatedFormat('F Y');
            $summary     = null;

        } elseif ($viewType === 'quarter') {
            $qMonths = $fiscalQuarterMonths[$quarter];
            $qYear   = ($quarter === 4) ? $year + 1 : $year;
            $start   = Carbon::createFromDate($qYear, $qMonths[0], 1)->startOfDay();
            $end     = Carbon::createFromDate($qYear, $qMonths[2], 1)->endOfMonth()->endOfDay();

            $activities = Activity::whereIn('user_id', $bsUserIds)
                ->where('status', Activity::Status_Approved)
                ->whereBetween('date', [$start, $end])
                ->get(['user_id', 'date', 'type_id']);

            $columns       = array_map(fn($m) => Carbon::createFromDate($qYear, $m, 1)->translatedFormat('M Y'), $qMonths);
            $colTypeTotals = array_fill(0, 3, array_fill_keys($typeIds, 0));
            $columnTotals  = [0, 0, 0];
            $grandTotal    = 0;
            $rows          = [];

            foreach ($bsUsers as $bs) {
                $userActs = $activities->where('user_id', $bs->id);
                $data     = [];
                $rowTotal = 0;

                foreach ($qMonths as $i => $m) {
                    $monthActs = $userActs->filter(function ($a) use ($m, $qYear) {
                        $d = Carbon::parse($a->date);
                        return $d->month === $m && $d->year === $qYear;
                    });

                    $typeCounts = [];
                    foreach ($typeIds as $tid) {
                        $count = $monthActs->where('type_id', $tid)->count();
                        $typeCounts[$tid] = $count;
                        $colTypeTotals[$i][$tid] += $count;
                    }
                    $monthTotal = $monthActs->count();
                    $data[] = ['type_counts' => $typeCounts, 'total' => $monthTotal];
                    $columnTotals[$i] += $monthTotal;
                    $rowTotal += $monthTotal;
                }

                $rows[] = ['name' => $bs->name, 'data' => $data, 'total' => $rowTotal];
                $grandTotal += $rowTotal;
            }

            $quarterLabels = [1 => 'Q1 (Apr-Jun)', 2 => 'Q2 (Jul-Sep)', 3 => 'Q3 (Okt-Des)', 4 => 'Q4 (Jan-Mar)'];
            $qStartMonth   = Carbon::createFromDate($qYear, $qMonths[0], 1)->translatedFormat('M Y');
            $qEndMonth     = Carbon::createFromDate($qYear, $qMonths[2], 1)->translatedFormat('M Y');
            $periodLabel   = "Kwartal {$quarter} ({$qStartMonth} – {$qEndMonth}) — Tahun Fiskal {$year}/" . ($year + 1);

            $qTargets = ActivityTarget::with('details')
                ->whereIn('user_id', $bsUserIds)
                ->where('year', $year)
                ->where('quarter', $quarter)
                ->get();
            $summary = $this->buildKpiSummary($bsUsers, $typeIds, $rows, $qTargets, $activityTypes);

        } else { // fiscal_year
            $start = Carbon::createFromDate($year, 4, 1)->startOfDay();
            $end   = Carbon::createFromDate($year + 1, 3, 31)->endOfDay();

            $activities = Activity::whereIn('user_id', $bsUserIds)
                ->where('status', Activity::Status_Approved)
                ->whereBetween('date', [$start, $end])
                ->get(['user_id', 'date', 'type_id']);

            $quarterRanges = [
                ['months' => [4, 5, 6],    'year' => $year,     'label' => 'Q1 (Apr-Jun)'],
                ['months' => [7, 8, 9],    'year' => $year,     'label' => 'Q2 (Jul-Sep)'],
                ['months' => [10, 11, 12], 'year' => $year,     'label' => 'Q3 (Okt-Des)'],
                ['months' => [1, 2, 3],    'year' => $year + 1, 'label' => 'Q4 (Jan-Mar)'],
            ];

            $columns       = array_column($quarterRanges, 'label');
            $colTypeTotals = array_fill(0, 4, array_fill_keys($typeIds, 0));
            $columnTotals  = [0, 0, 0, 0];
            $grandTotal    = 0;
            $rows          = [];

            foreach ($bsUsers as $bs) {
                $userActs = $activities->where('user_id', $bs->id);
                $data     = [];
                $rowTotal = 0;

                foreach ($quarterRanges as $i => $qr) {
                    $qActs = $userActs->filter(function ($a) use ($qr) {
                        $d = Carbon::parse($a->date);
                        return in_array($d->month, $qr['months']) && $d->year === $qr['year'];
                    });

                    $typeCounts = [];
                    foreach ($typeIds as $tid) {
                        $count = $qActs->where('type_id', $tid)->count();
                        $typeCounts[$tid] = $count;
                        $colTypeTotals[$i][$tid] += $count;
                    }
                    $qTotal = $qActs->count();
                    $data[] = ['type_counts' => $typeCounts, 'total' => $qTotal];
                    $columnTotals[$i] += $qTotal;
                    $rowTotal += $qTotal;
                }

                $rows[] = ['name' => $bs->name, 'data' => $data, 'total' => $rowTotal];
                $grandTotal += $rowTotal;
            }

            $periodLabel = "Tahun Fiskal {$year}/" . ($year + 1);

            $fyTargets = ActivityTarget::with('details')
                ->whereIn('user_id', $bsUserIds)
                ->where('year', $year)
                ->get();
            $summary = $this->buildKpiSummary($bsUsers, $typeIds, $rows, $fyTargets, $activityTypes);
        }

        return inertia('admin/dashboard/Index', [
            'data' => [
                'view_type'       => $viewType,
                'period_label'    => $periodLabel,
                'activity_types'  => $activityTypes->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values(),
                'columns'         => $columns,
                'rows'            => $rows,
                'col_type_totals' => array_values($colTypeTotals),
                'column_totals'   => $columnTotals,
                'grand_total'     => $grandTotal,
                'summary'         => $summary,
            ],
        ]);
    }

    private function buildKpiSummary($bsUsers, array $typeIds, array $rows, $targets, $activityTypes): array
    {
        // Build target map: user_id -> type_id -> total_target_qty
        $targetMap = [];
        foreach ($targets as $target) {
            $uid = $target->user_id;
            foreach ($target->details as $d) {
                $tid = $d->type_id;
                $qty = (int)$d->month1_qty + (int)$d->month2_qty + (int)$d->month3_qty;
                $targetMap[$uid][$tid] = ($targetMap[$uid][$tid] ?? 0) + $qty;
            }
        }

        // Weight map: type_id -> weight
        $weightMap = $activityTypes->pluck('weight', 'id')->map(fn($w) => (float)$w)->toArray();

        $summaryRows = [];
        $totTypeAct  = array_fill_keys($typeIds, 0);
        $totTypeTgt  = array_fill_keys($typeIds, 0);
        $totActual   = 0;
        $totTarget   = 0;

        foreach ($bsUsers as $i => $bs) {
            $rowData = $rows[$i] ?? null;

            // Sum actuals per type across all periods
            $typeAct = array_fill_keys($typeIds, 0);
            if ($rowData) {
                foreach ($rowData['data'] as $period) {
                    foreach ($typeIds as $tid) {
                        $typeAct[$tid] += $period['type_counts'][$tid] ?? 0;
                    }
                }
            }

            // Target per active type
            $typeTgt = array_fill_keys($typeIds, 0);
            foreach (($targetMap[$bs->id] ?? []) as $tid => $qty) {
                if (array_key_exists($tid, $typeTgt)) {
                    $typeTgt[$tid] = $qty;
                }
            }

            $ta = $rowData['total'] ?? 0;
            $tt = array_sum($typeTgt);

            // Weighted KPI: normalize by sum of weights for types that have targets
            $kpi = $this->calcWeightedKpi($typeIds, $typeAct, $typeTgt, $weightMap);

            foreach ($typeIds as $tid) {
                $totTypeAct[$tid] += $typeAct[$tid];
                $totTypeTgt[$tid] += $typeTgt[$tid];
            }
            $totActual += $ta;
            $totTarget += $tt;

            $summaryRows[] = [
                'name'         => $bs->name,
                'type_actuals' => $typeAct,
                'type_targets' => $typeTgt,
                'total_actual' => $ta,
                'total_target' => $tt,
                'kpi'          => $kpi,
            ];
        }

        return [
            'rows'   => $summaryRows,
            'totals' => [
                'type_actuals' => $totTypeAct,
                'type_targets' => $totTypeTgt,
                'total_actual' => $totActual,
                'total_target' => $totTarget,
                'kpi'          => $this->calcWeightedKpi($typeIds, $totTypeAct, $totTypeTgt, $weightMap),
            ],
        ];
    }

    /**
     * KPI = Σ(min(aktual/target, 1) × bobot) / Σ(bobot tipe yang punya target) × 100
     * Hanya tipe yang ada targetnya yang dimasukkan ke penyebut.
     */
    private function calcWeightedKpi(array $typeIds, array $typeAct, array $typeTgt, array $weightMap): ?int
    {
        $weightedProgress = 0.0;
        $relevantWeight   = 0.0;

        foreach ($typeIds as $tid) {
            $target = $typeTgt[$tid] ?? 0;
            $weight = $weightMap[$tid] ?? 0;

            if ($target > 0 && $weight > 0) {
                $actual = $typeAct[$tid] ?? 0;
                $weightedProgress += min($actual / $target, 1.0) * $weight;
                $relevantWeight   += $weight;
            }
        }

        return $relevantWeight > 0 ? (int) round($weightedProgress / $relevantWeight * 100) : null;
    }

    private function getQuarterFromMonth(int $month): int
    {
        if (in_array($month, [4, 5, 6]))   return 1;
        if (in_array($month, [7, 8, 9]))   return 2;
        if (in_array($month, [10, 11, 12])) return 3;
        return 4;
    }

    private function managerDashboard(Request $request)
    {
        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();
        $startOfYear  = Carbon::createFromDate($year, 1, 1)->startOfDay();

        $totalSalesMonth = Sale::whereBetween('date', [$startOfMonth, $endOfMonth])->sum('total_amount');
        $totalSalesYear  = Sale::whereBetween('date', [$startOfYear, $endOfMonth])->sum('total_amount');
        $countMonth      = Sale::whereBetween('date', [$startOfMonth, $endOfMonth])->count();

        // Monthly trend (last 6 months)
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $m     = $startOfMonth->copy()->subMonths($i);
            $mEnd  = $m->copy()->endOfMonth();
            $monthlyTrend[] = [
                'label'  => $m->format('M Y'),
                'total'  => (float) Sale::whereBetween('date', [$m, $mEnd])->sum('total_amount'),
                'count'  => Sale::whereBetween('date', [$m, $mEnd])->count(),
            ];
        }

        // Top 5 distributors this month
        $topDistributors = Sale::selectRaw('distributor_id, SUM(total_amount) as total_sales, COUNT(*) as tx_count')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->groupBy('distributor_id')
            ->orderByDesc('total_sales')
            ->with('distributor:id,name')
            ->limit(5)
            ->get();

        return inertia('admin/dashboard/Index', [
            'dashboardType' => 'manager',
            'data' => [
                'total_sales_month'  => (float) $totalSalesMonth,
                'total_sales_year'   => (float) $totalSalesYear,
                'count_month'        => $countMonth,
                'monthly_trend'      => $monthlyTrend,
                'top_distributors'   => $topDistributors,
                'current_month_label' => $startOfMonth->translatedFormat('F Y'),
            ],
        ]);
    }

    private function distributorDashboard(Request $request)
    {
        $user = auth()->user();

        if (!$user->customer_id) {
            return inertia('admin/dashboard/Index', [
                'dashboardType' => 'distributor',
                'data' => ['error' => 'Akun distributor belum terhubung ke data pelanggan.'],
            ]);
        }

        $distributorId = $user->customer_id;

        // Stock summary
        $stocks = DistributorStock::where('distributor_id', $distributorId)
            ->with('product:id,name,unit')
            ->orderByDesc('stock_quantity')
            ->get();

        // Recent sales (last 10)
        $recentSales = Sale::where('distributor_id', $distributorId)
            ->with('retailer:id,name')
            ->orderByDesc('date')
            ->limit(10)
            ->get(['id', 'date', 'retailer_id', 'total_amount']);

        // This month summary
        $startOfMonth = now()->startOfMonth();
        $endOfMonth   = now()->endOfMonth();

        $salesThisMonth = Sale::where('distributor_id', $distributorId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $countThisMonth = Sale::where('distributor_id', $distributorId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->count();

        return inertia('admin/dashboard/Index', [
            'dashboardType' => 'distributor',
            'data' => [
                'stocks'           => $stocks,
                'recent_sales'     => $recentSales,
                'sales_this_month' => (float) $salesThisMonth,
                'count_this_month' => $countThisMonth,
            ],
        ]);
    }

    /// TIDAK DIGUNAKAN
    public function _index(Request $request)
    {
        $period = $request->get('period', 'this_month');
        [$start_date, $end_date] = resolve_period($period);

        $start_date = $start_date ? Carbon::parse($start_date)->startOfMonth() : Carbon::createFromDate(2000, 1, 1);
        $end_date = $start_date->copy()->endOfMonth();

        $user = $request->user();

        if ($user->role === User::Role_BS) {
            $fiscalInfo = getFiscalQuarterInfo($start_date);

            $year = $fiscalInfo['fiscal_year'];
            $quarter = $fiscalInfo['quarter'];
            $month_position = $fiscalInfo['month_position'];

            $targets = ActivityTarget::with(['details.type'])
                ->where('user_id', $user->id)
                ->where('year', $year)
                ->where('quarter', $quarter)
                ->get();

            $summary = [];
            $month_column = 'month' . $month_position . '_qty';

            $total_target = 0;
            $total_completed = 0;
            $total_planned = 0;
            $total_weight = 0;
            $weighted_progress = 0;

            foreach ($targets as $target) {
                foreach ($target->details as $detail) {
                    $type_id = $detail->type_id;
                    $weight = (int) $detail->type->weight ?? 0;

                    if (!isset($summary[$type_id])) {
                        $summary[$type_id] = [
                            'type_id' => $type_id,
                            'type_name' => $detail->type->code ?? $detail->type->name,
                            'target_qty' => 0,
                            'plan_qty' => 0,
                            'real_qty' => 0,
                            'weight' => $weight,
                        ];
                        $total_weight += $weight;
                    }

                    $qty = (int) $detail->{$month_column};

                    $summary[$type_id]['target_qty'] += $qty;
                    $total_target += $qty;
                }
            }

            $start = Carbon::createFromDate($fiscalInfo['start_year'], $fiscalInfo['start_month'], 1)->startOfDay();
            $end = Carbon::createFromDate($fiscalInfo['end_year'], $fiscalInfo['end_month'], 1)->endOfMonth()->endOfDay();

            $plans = ActivityPlan::with('details')
                ->where('user_id', $user->id)
                ->where('status', ActivityPlan::Status_Approved)
                ->whereBetween('date', [$start_date, $end_date])
                ->get();

            foreach ($plans as $plan) {
                foreach ($plan->details as $detail) {
                    $type_id = $detail->type_id;

                    if (!isset($summary[$type_id])) {
                        $summary[$type_id] = [
                            'type_id' => $type_id,
                            'type_name' => $detail->type->name,
                            'target_qty' => 0,
                            'plan_qty' => 0,
                            'real_qty' => 0,
                            'weight' => (int) $detail->type->weight ?? 0,
                        ];
                        $total_weight += $summary[$type_id]['weight'];
                    }

                    $summary[$type_id]['plan_qty'] += 1;
                    $total_planned += 1;
                }
            }

            $activities = Activity::with(['type'])
                ->where('user_id', $user->id)
                ->where('status', Activity::Status_Approved)
                ->whereBetween('date', [$start_date, $end_date])
                ->get();

            foreach ($activities as $activity) {
                $type_id = $activity->type_id;
                $weight = (int) $activity->type->weight ?? 0;

                if (!isset($summary[$type_id])) {
                    $summary[$type_id] = [
                        'type_id' => $type_id,
                        'type_name' => $activity->type->name,
                        'target_qty' => 0,
                        'plan_qty' => 0,
                        'real_qty' => 0,
                        'weight' => $weight,
                    ];
                    $total_weight += $weight;
                }

                $summary[$type_id]['real_qty'] += 1;
                $total_completed += 1;
            }

            if ($total_weight > 0) {
                foreach ($summary as $row) {
                    $target = $row['target_qty'];
                    $real = $row['real_qty'];
                    $weight = $row['weight'];

                    if ($target > 0) {
                        $progress = min($real / $target, 1);
                        $normalized_weight = $weight / $total_weight;
                        $weighted_progress += $progress * $normalized_weight;
                    }
                }
            }

            // === HITUNG PROGRES KUARTALAN ===

            $quarter_summary = [];
            $total_quarter_target = 0;
            $total_quarter_completed = 0;
            $total_quarter_weight = 0;
            $quarter_weighted_progress = 0;

            foreach ($targets as $target) {
                foreach ($target->details as $detail) {
                    $type_id = $detail->type_id;
                    $weight = (int) $detail->type->weight ?? 0;

                    if (!isset($quarter_summary[$type_id])) {
                        $quarter_summary[$type_id] = [
                            'type_id' => $type_id,
                            'type_name' => $detail->type->code ?? $detail->type->name,
                            'target_qty' => 0,
                            'real_qty' => 0,
                            'weight' => $weight,
                        ];
                        $total_quarter_weight += $weight;
                    }

                    $qty =
                        (int) $detail->month1_qty +
                        (int) $detail->month2_qty +
                        (int) $detail->month3_qty;

                    $quarter_summary[$type_id]['target_qty'] += $qty;
                    $total_quarter_target += $qty;
                }
            }

            foreach ($activities as $activity) {
                $type_id = $activity->type_id;
                $weight = (int) $activity->type->weight ?? 0;

                if (!isset($quarter_summary[$type_id])) {
                    $quarter_summary[$type_id] = [
                        'type_id' => $type_id,
                        'type_name' => $activity->type->name,
                        'target_qty' => 0,
                        'real_qty' => 0,
                        'weight' => $weight,
                    ];
                    $total_quarter_weight += $weight;
                }

                $quarter_summary[$type_id]['real_qty'] += 1;
                $total_quarter_completed += 1;
            }

            if ($total_quarter_weight > 0) {
                foreach ($quarter_summary as $row) {
                    $target = $row['target_qty'];
                    $real = $row['real_qty'];
                    $weight = $row['weight'];

                    if ($target > 0) {
                        $progress = min($real / $target, 1);
                        $normalized_weight = $weight / $total_quarter_weight;
                        $quarter_weighted_progress += $progress * $normalized_weight;
                    }
                }
            }

            return inertia('admin/dashboard/Index', [
                'data' => [
                    'targets' => array_values($summary),
                    'total_target' => $total_target,
                    'total_completed' => $total_completed,
                    'total_progress' => round($weighted_progress * 100), // Bulanan
                    'total_quarter_progress' => round($quarter_weighted_progress * 100),
                ],
                'period' => [
                    'label' => \Illuminate\Support\Str::headline(str_replace('_', ' ', $period)),
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                ],
            ]);
        }




        // $labels = [];
        // $count_interactions = [];
        // $count_closings = [];
        // $count_new_customers = [];
        // $total_closings = [];

        // $start = $start_date ? Carbon::parse($start_date) : Carbon::createFromDate(2000, 1, 1);
        // $end = $end_date ? Carbon::parse($end_date) : Carbon::now();

        // if (in_array($period, ['all_time', 'this_year', 'last_year'])) {
        //     // BULANAN
        //     $current = $start->copy();

        //     while ($current->lessThanOrEqualTo($end)) {
        //         $labels[] = $current->format('F Y'); // e.g., January 2024

        //         $monthStart = $current->copy()->startOfMonth();
        //         $monthEnd = $current->copy()->endOfMonth();

        //         $countInteraction = Interaction::where('status', 'done')
        //             ->whereBetween('date', [$monthStart, $monthEnd])
        //             ->count();

        //         $countClosing = Closing::whereBetween('date', [$monthStart, $monthEnd])
        //             ->count();

        //         $countNewCustomer = Customer::whereBetween('created_datetime', [$monthStart, $monthEnd])
        //             ->count();

        //         $sum_closing = Closing::whereBetween('date', [$monthStart, $monthEnd])
        //             ->sum('amount');

        //         $count_interactions[]  = $countInteraction;
        //         $count_closings[]      = $countClosing;
        //         $count_new_customers[] = $countNewCustomer;
        //         $total_closings[]      = $sum_closing;

        //         $current->addMonth();
        //     }
        // } else {
        //     // HARIAN
        //     $current = $start->copy();

        //     while ($current->lessThanOrEqualTo($end)) {
        //         $labels[] = $current->format('d'); // e.g., 01, 02, ..., 31

        //         $countInteraction = Interaction::where('status', 'done')
        //             ->whereDate('date', $current->format('Y-m-d'))
        //             ->count();

        //         $countClosing = Closing::whereDate('date', $current->format('Y-m-d'))
        //             ->count();

        //         $countNewCustomer = Customer::whereDate('created_datetime', $current->format('Y-m-d'))
        //             ->count();

        //         $sum_closing = Closing::whereDate('date', $current->format('Y-m-d'))
        //             ->sum('amount');

        //         $count_interactions[]  = $countInteraction;
        //         $count_closings[]      = $countClosing;
        //         $count_new_customers[] = $countNewCustomer;
        //         $total_closings[]      = $sum_closing;

        //         $current->addDay();
        //     }
        // }

        return inertia('admin/dashboard/Index', [
            'chart_data' => [
                // 'labels' => $labels,
                // 'count_interactions' => $count_interactions,
                // 'count_closings' => $count_closings,
                // 'count_new_customers' => $count_new_customers,
                // 'total_closings' => $total_closings,
                // 'interactions' => Interaction::interactionCountByStatus($start_date, $end_date),
                // 'top_interactions'  => Interaction::getTopInteractions($start_date, $end_date, 5),
                // 'top_sales_closings'  => Closing::getTop5SalesClosings($start_date, $end_date, 5),
            ],
            'data' => [
                // 'recent_interactions' => Interaction::recentInteractions(5),
                // 'recent_closings' => Closing::recentClosings(5),
                // 'recent_customers' => Customer::recentCustomers(5),
                // 'active_interaction_plan_count' => Interaction::activePlanCount(),
                // 'active_customer_service_count' => CustomerService::activeCustomerServiceCount(),
                // 'active_customer_count' => Customer::activeCustomerCount(),
                // 'active_sales_count' => User::activeSalesCount(),
                // 'active_user_count' => User::activeUserCount(),
                // 'active_service_count' => Service::activeServiceCount(),

                // 'interaction_count' => Interaction::interactionCount($start_date, $end_date),
                // 'new_customer_count' => Customer::newCustomerCount($start_date, $end_date),
                // 'closing_count' => Closing::closingCount($start_date, $end_date),
                // 'closing_amount' => Closing::closingAmount($start_date, $end_date),
            ]
        ]);
    }

    /**
     * This method exists here for testing purpose only.
     */
    public function test()
    {
        return inertia('admin/dashboard/Test');
    }
}
