<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DistributorTarget;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class DistributorTargetController extends Controller
{
    public function index()
    {
        $distributors = Customer::where('type', Customer::Type_Distributor)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $products = Product::where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'uom_1', 'price_1']);

        return inertia('admin/distributor-target/Index', [
            'distributors' => $distributors,
            'products'     => $products,
        ]);
    }

    /**
     * Return rows grouped by distributor+product with annual totals vs actual.
     * Query params: fiscal_year, distributor_id (optional)
     */
    public function data(Request $request)
    {
        $fiscalYear    = (int) $request->get('fiscal_year', $this->currentFiscalYear());
        $distributorId = $request->get('distributor_id');

        [$startDate, $endDate] = $this->fiscalYearDateRange($fiscalYear);

        // ── Targets for the fiscal year ──────────────────────────────────────
        $targets = DistributorTarget::where('fiscal_year', $fiscalYear)
            ->when($distributorId, fn($q) => $q->where('distributor_id', $distributorId))
            ->with(['distributor:id,name', 'product:id,name,uom_1'])
            ->get();

        if ($targets->isEmpty()) {
            return response()->json([
                'rows'          => [],
                'total_target'  => 0,
                'total_actual'  => 0,
                'fiscal_year'   => $fiscalYear,
            ]);
        }

        // ── Group by distributor+product ─────────────────────────────────────
        $grouped = $targets->groupBy(fn($t) => "{$t->distributor_id}_{$t->product_id}");

        // ── Actual qty from sale_items for the fiscal year ───────────────────
        $distIds = $targets->pluck('distributor_id')->unique();
        $prodIds = $targets->pluck('product_id')->unique();

        $actuals = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.sale_type', Sale::Type_Distributor)
            ->whereBetween('sales.date', [$startDate, $endDate])
            ->whereIn('sales.distributor_id', $distIds)
            ->whereIn('sale_items.product_id', $prodIds)
            ->groupBy('sales.distributor_id', 'sale_items.product_id')
            ->select(
                'sales.distributor_id',
                'sale_items.product_id',
                DB::raw('SUM(sale_items.quantity) as actual_qty')
            )
            ->get()
            ->keyBy(fn($r) => "{$r->distributor_id}_{$r->product_id}");

        // ── Combine ───────────────────────────────────────────────────────────
        $rows = $grouped->map(function ($items, $key) use ($actuals) {
            $first       = $items->first();
            $totalTarget = (float) $items->sum('target_qty');
            $actualQty   = $actuals->has($key) ? (float) $actuals[$key]->actual_qty : 0;

            return [
                'key'             => $key,
                'distributor_id'  => $first->distributor_id,
                'distributor_name'=> $first->distributor?->name ?? '—',
                'product_id'      => $first->product_id,
                'product_name'    => $first->product?->name ?? '—',
                'uom'             => $first->product?->uom_1 ?? 'kg',
                'total_target_qty'=> $totalTarget,
                'total_actual_qty'=> $actualQty,
                'achievement'     => $totalTarget > 0
                    ? round($actualQty / $totalTarget * 100, 1)
                    : null,
                'months_set'      => $items->count(),
            ];
        })
        ->sortBy([['distributor_name', 'asc'], ['product_name', 'asc']])
        ->values();

        // ── Orphan actuals: sales for products WITHOUT targets in this FY ────
        // These are sales where the product has no target entry yet, so they
        // wouldn't normally appear in the target table.
        $orphanActuals = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('customers', 'sales.distributor_id', '=', 'customers.id')
            ->where('sales.sale_type', Sale::Type_Distributor)
            ->whereBetween('sales.date', [$startDate, $endDate])
            ->whereIn('sales.distributor_id', $distIds)
            ->whereNotIn('sale_items.product_id', $prodIds)
            ->groupBy('sales.distributor_id', 'sale_items.product_id', 'customers.name', 'products.name', 'products.uom_1')
            ->select(
                'sales.distributor_id',
                'customers.name as distributor_name',
                'sale_items.product_id',
                'products.name as product_name',
                'products.uom_1 as uom',
                DB::raw('SUM(sale_items.quantity) as actual_qty')
            )
            ->get();

        if ($orphanActuals->isNotEmpty()) {
            $orphanRows = $orphanActuals->map(fn($r) => [
                'key'              => "{$r->distributor_id}_{$r->product_id}",
                'distributor_id'   => $r->distributor_id,
                'distributor_name' => $r->distributor_name,
                'product_id'       => $r->product_id,
                'product_name'     => $r->product_name,
                'uom'              => $r->uom ?? 'kg',
                'total_target_qty' => 0,
                'total_actual_qty' => (float) $r->actual_qty,
                'achievement'      => null,
                'months_set'       => 0,
            ])->values();

            $rows = $rows->merge($orphanRows)
                ->sortBy([['distributor_name', 'asc'], ['product_name', 'asc']])
                ->values();
        }

        return response()->json([
            'rows'         => $rows,
            'total_target' => $rows->sum('total_target_qty'),
            'total_actual' => $rows->sum('total_actual_qty'),
            'fiscal_year'  => $fiscalYear,
        ]);
    }

    /**
     * Per-month breakdown for a specific distributor+product+fiscal_year (for the editor dialog).
     */
    public function months(Request $request)
    {
        $distributorId = $request->get('distributor_id');
        $productId     = $request->get('product_id');
        $fiscalYear    = (int) $request->get('fiscal_year', $this->currentFiscalYear());

        $existing = DistributorTarget::where('distributor_id', $distributorId)
            ->where('product_id', $productId)
            ->where('fiscal_year', $fiscalYear)
            ->get()
            ->keyBy('month');

        $result = [];
        foreach ([4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3] as $m) {
            $year  = $m >= 4 ? $fiscalYear : $fiscalYear + 1;
            $start = Carbon::create($year, $m, 1)->startOfMonth()->toDateString();
            $end   = Carbon::create($year, $m, 1)->endOfMonth()->toDateString();

            $actualQty = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->where('sales.sale_type', Sale::Type_Distributor)
                ->where('sales.distributor_id', $distributorId)
                ->where('sale_items.product_id', $productId)
                ->whereBetween('sales.date', [$start, $end])
                ->sum('sale_items.quantity');

            $row = $existing->get($m);
            $result[$m] = [
                'target_qty' => $row ? (float) $row->target_qty : null,
                'actual_qty' => (float) $actualQty,
                'target_id'  => $row?->id,
            ];
        }

        return response()->json($result);
    }

    /**
     * Upsert targets for multiple months at once.
     * Body: { distributor_id, product_id, fiscal_year, months: { 4: qty|null, 5: qty|null, ... } }
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'distributor_id' => 'required|exists:customers,id',
            'product_id'     => 'required|exists:products,id',
            'fiscal_year'    => 'required|integer|min:2020|max:2099',
            'months'         => 'required|array',
            'notes'          => 'nullable|string|max:500',
        ]);

        $uid  = auth()->id();
        $now  = now();
        $dist = $validated['distributor_id'];
        $prod = $validated['product_id'];
        $year = $validated['fiscal_year'];
        $notes = $validated['notes'] ?? null;

        DB::transaction(function () use ($validated, $dist, $prod, $year, $notes, $uid, $now) {
            foreach ($validated['months'] as $month => $qty) {
                $month = (int) $month;
                if ($month < 1 || $month > 12) continue;

                if ($qty === null || $qty === '') {
                    // Clear this month's target if it exists
                    DistributorTarget::where([
                        'distributor_id' => $dist,
                        'product_id'     => $prod,
                        'fiscal_year'    => $year,
                        'month'          => $month,
                    ])->delete();
                } else {
                    $existing = DistributorTarget::where([
                        'distributor_id' => $dist,
                        'product_id'     => $prod,
                        'fiscal_year'    => $year,
                        'month'          => $month,
                    ])->first();

                    if ($existing) {
                        $existing->update([
                            'target_qty'       => $qty,
                            'notes'            => $notes,
                            'updated_by_uid'   => $uid,
                            'updated_datetime' => $now,
                        ]);
                    } else {
                        DistributorTarget::create([
                            'distributor_id'   => $dist,
                            'product_id'       => $prod,
                            'fiscal_year'      => $year,
                            'month'            => $month,
                            'target_qty'       => $qty,
                            'notes'            => $notes,
                            'created_by_uid'   => $uid,
                            'created_datetime' => $now,
                            'updated_by_uid'   => $uid,
                            'updated_datetime' => $now,
                        ]);
                    }
                }
            }
        });

        return response()->json(['message' => 'Target disimpan.']);
    }

    /**
     * Delete all month targets for a distributor+product+fiscal_year combination.
     */
    public function delete(Request $request)
    {
        $request->validate([
            'distributor_id' => 'required|integer',
            'product_id'     => 'required|integer',
            'fiscal_year'    => 'required|integer',
        ]);

        DistributorTarget::where('distributor_id', $request->distributor_id)
            ->where('product_id', $request->product_id)
            ->where('fiscal_year', $request->fiscal_year)
            ->delete();

        return response()->json(['message' => 'Target dihapus.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Import Breakdown Target Excel (Distributor | Tahun Fiskal | Varietas | Satuan | Bulan | Target | Realisasi).
     * Mode: 'target' | 'realisasi' | 'both'
     */
    public function importXlsx(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx',
            'mode' => 'required|in:target,realisasi,both',
        ]);

        $reader      = new XlsxReader();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($request->file('file')->getRealPath());
        $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        array_shift($rows); // remove header row

        $mode = $request->get('mode');
        $uid  = auth()->id();
        $now  = now();

        // ── Lookups ────────────────────────────────────────────────────────────
        $distributors = Customer::where('type', Customer::Type_Distributor)
            ->get(['id', 'name'])
            ->mapWithKeys(fn($d) => [strtolower(trim($d->name)) => $d]);

        $products = Product::get(['id', 'name', 'uom_1', 'price_1', 'price_2'])
            ->mapWithKeys(fn($p) => [strtolower(trim($p->name)) => $p]);

        $monthMap = [
            'apr' => 4, 'mei' => 5, 'jun' => 6, 'jul' => 7,
            'agu' => 8, 'sep' => 9, 'okt' => 10, 'nov' => 11,
            'des' => 12, 'jan' => 1, 'feb' => 2, 'mar' => 3,
        ];

        $targetImported = 0;
        $salesImported  = 0;
        $errors         = [];

        // ── Parse rows ─────────────────────────────────────────────────────────
        // Collect valid parsed rows first (for realisasi grouping)
        $parsed = [];
        foreach ($rows as $i => $row) {
            $rowNum   = $i + 2;
            $distName = trim($row[0] ?? '');
            $fyStr    = trim($row[1] ?? '');    // "2025/2026"
            $prodName = trim($row[2] ?? '');
            $unit     = strtolower(trim($row[3] ?? 'kg'));
            $monthStr = strtolower(trim($row[4] ?? ''));
            $targetQty    = (float) ($row[5] ?? 0);
            $realisasiQty = (float) ($row[6] ?? 0);

            if (!$distName || !$prodName || strtoupper($prodName) === 'N/A') continue;
            if (!isset($monthMap[$monthStr])) continue;

            // Parse "2025/2026" → 2025
            if (!preg_match('/^(\d{4})\//', $fyStr, $m)) continue;
            $fiscalYear = (int) $m[1];
            $month      = $monthMap[$monthStr];
            $calYear    = $month >= 4 ? $fiscalYear : $fiscalYear + 1;

            // Match distributor
            $dist = $distributors->get(strtolower($distName))
                ?? $distributors->first(fn($d) =>
                    str_contains(strtolower($d->name), strtolower($distName)) ||
                    str_contains(strtolower($distName), strtolower($d->name))
                );
            if (!$dist) {
                $errors[] = "Baris {$rowNum}: Distributor \"{$distName}\" tidak ditemukan.";
                continue;
            }

            // Match product
            $product = $products->get(strtolower($prodName))
                ?? $products->first(fn($p) =>
                    str_contains(strtolower($p->name), strtolower($prodName)) ||
                    str_contains(strtolower($prodName), strtolower($p->name))
                );
            if (!$product) {
                $errors[] = "Baris {$rowNum}: Produk \"{$prodName}\" tidak ditemukan.";
                continue;
            }

            $parsed[] = compact(
                'rowNum', 'dist', 'product', 'unit',
                'fiscalYear', 'month', 'calYear',
                'targetQty', 'realisasiQty'
            );
        }

        DB::transaction(function () use (
            $parsed, $mode, $uid, $now,
            &$targetImported, &$salesImported
        ) {
            // ── 1. Target import ───────────────────────────────────────────────
            if (in_array($mode, ['target', 'both'])) {
                foreach ($parsed as $p) {
                    if ($p['targetQty'] <= 0) continue;

                    $existing = DistributorTarget::where([
                        'distributor_id' => $p['dist']->id,
                        'product_id'     => $p['product']->id,
                        'fiscal_year'    => $p['fiscalYear'],
                        'month'          => $p['month'],
                    ])->first();

                    if ($existing) {
                        $existing->update([
                            'target_qty'       => $p['targetQty'],
                            'updated_by_uid'   => $uid,
                            'updated_datetime' => $now,
                        ]);
                    } else {
                        DistributorTarget::create([
                            'distributor_id'   => $p['dist']->id,
                            'product_id'       => $p['product']->id,
                            'fiscal_year'      => $p['fiscalYear'],
                            'month'            => $p['month'],
                            'target_qty'       => $p['targetQty'],
                            'notes'            => 'Import Excel',
                            'created_by_uid'   => $uid,
                            'created_datetime' => $now,
                            'updated_by_uid'   => $uid,
                            'updated_datetime' => $now,
                        ]);
                    }
                    $targetImported++;
                }
            }

            // ── 2. Realisasi import → grouped by distributor+month ─────────────
            if (in_array($mode, ['realisasi', 'both'])) {
                // Group items: key = "{dist_id}_{calYear}_{month}"
                $groups = [];
                foreach ($parsed as $p) {
                    if ($p['realisasiQty'] <= 0) continue;
                    $key = "{$p['dist']->id}_{$p['calYear']}_{$p['month']}";
                    $groups[$key][] = $p;
                }

                foreach ($groups as $items) {
                    $first       = $items[0];
                    $saleDate    = Carbon::create($first['calYear'], $first['month'], 1)
                        ->endOfMonth()->toDateString();
                    $totalAmount = 0;

                    $saleItems = [];
                    foreach ($items as $item) {
                        $price    = (float) ($item['product']->price_1 > 0
                            ? $item['product']->price_1
                            : $item['product']->price_2);
                        $subtotal = round($item['realisasiQty'] * $price, 2);
                        $totalAmount += $subtotal;
                        $saleItems[] = [
                            'product_id' => $item['product']->id,
                            'quantity'   => $item['realisasiQty'],
                            'unit'       => $item['unit'],
                            'price'      => $price,
                            'subtotal'   => $subtotal,
                        ];
                    }

                    // Check: skip if a sale already exists for this dist+date with import note
                    $exists = Sale::where('distributor_id', $first['dist']->id)
                        ->where('date', $saleDate)
                        ->where('sale_type', Sale::Type_Retailer)
                        ->where('notes', 'like', 'Import%')
                        ->exists();
                    if ($exists) continue;

                    $sale = Sale::create([
                        'sale_type'        => Sale::Type_Retailer,
                        'date'             => $saleDate,
                        'distributor_id'   => $first['dist']->id,
                        'total_amount'     => $totalAmount,
                        'notes'            => 'Import: FY ' . $first['fiscalYear'] . '/' . ($first['fiscalYear'] + 1),
                        'created_by_uid'   => $uid,
                        'created_datetime' => $now,
                        'updated_by_uid'   => $uid,
                        'updated_datetime' => $now,
                    ]);

                    foreach ($saleItems as $si) {
                        SaleItem::create(array_merge($si, ['sale_id' => $sale->id]));
                    }
                    $salesImported++;
                }
            }
        });

        return response()->json([
            'target_imported' => $targetImported,
            'sales_imported'  => $salesImported,
            'errors'          => $errors,
        ]);
    }

    /**
     * Import targets from a PDF exported by this app.
     * Divides annual total evenly across all 12 fiscal months.
     */
    public function importPdf(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file'        => 'required|file|mimes:pdf',
            'fiscal_year' => 'nullable|integer|min:2020|max:2099',
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());

        // Extract all text tokens: (text) Tj
        preg_match_all('/\(([^)]*)\)\s*Tj/', $content, $m);
        $texts = array_map(fn($t) => trim(stripslashes($t)), $m[1]);

        // Auto-detect fiscal year from PDF header (e.g. "Tahun Fiskal: 2025/2026")
        $fiscalYear = $request->integer('fiscal_year', 0);
        foreach ($texts as $t) {
            if (preg_match('/Tahun Fiskal:\s*(\d{4})/', $t, $fyM)) {
                if (!$fiscalYear) $fiscalYear = (int) $fyM[1];
                break;
            }
        }
        if (!$fiscalYear) $fiscalYear = $this->currentFiscalYear();

        // Find where data rows start (after the last header "Pencapaian")
        $startIdx = null;
        foreach ($texts as $i => $t) {
            if (str_contains($t, 'Pencapaian')) {
                $startIdx = $i + 1;
                break;
            }
        }
        if ($startIdx === null) {
            return response()->json([
                'success' => 0,
                'errors'  => ['Format PDF tidak dikenali. Pastikan file adalah ekspor "Laporan Target Penjualan" dari aplikasi ini.'],
            ]);
        }

        $dataTexts = array_slice($texts, $startIdx);

        // Load all distributors + products for fast lookup (case-insensitive key)
        $distributors = Customer::where('type', Customer::Type_Distributor)
            ->get(['id', 'name'])
            ->keyBy(fn($d) => strtolower(trim($d->name)));

        $products = Product::get(['id', 'name'])
            ->keyBy(fn($p) => strtolower(trim($p->name)));

        $uid     = auth()->id();
        $now     = now();
        $success = 0;
        $errors  = [];

        // Data rows come in groups of 6: distributor, varietas, satuan, target, realisasi, pencapaian
        $chunks = array_chunk($dataTexts, 6);

        DB::transaction(function () use (
            $chunks, $distributors, $products, $fiscalYear, $uid, $now, &$success, &$errors
        ) {
            foreach ($chunks as $idx => $chunk) {
                if (count($chunk) < 4) continue;

                [$distName, $prodName, , $targetRaw] = $chunk;
                $distName = trim($distName);
                $prodName = trim($prodName);

                if ($prodName === 'N/A' || $prodName === '') continue;

                $targetQty = $this->parseIndonesianNumber($targetRaw);
                if ($targetQty <= 0) continue;

                // Match distributor (exact, then partial)
                $distKey     = strtolower($distName);
                $distributor = $distributors->get($distKey)
                    ?? $distributors->first(
                        fn($d) => str_contains(strtolower($d->name), $distKey)
                               || str_contains($distKey, strtolower($d->name))
                    );

                if (!$distributor) {
                    $errors[] = "Baris " . ($idx + 1) . ": Distributor \"{$distName}\" tidak ditemukan di database.";
                    continue;
                }

                // Match product (exact, then partial)
                $prodKey = strtolower($prodName);
                $product = $products->get($prodKey)
                    ?? $products->first(
                        fn($p) => str_contains(strtolower($p->name), $prodKey)
                               || str_contains($prodKey, strtolower($p->name))
                    );

                if (!$product) {
                    $errors[] = "Baris " . ($idx + 1) . ": Produk \"{$prodName}\" tidak ditemukan di database.";
                    continue;
                }

                // Distribute total target evenly across 12 fiscal months
                $monthlyQty = round($targetQty / 12, 2);

                foreach ([4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3] as $month) {
                    $existing = DistributorTarget::where([
                        'distributor_id' => $distributor->id,
                        'product_id'     => $product->id,
                        'fiscal_year'    => $fiscalYear,
                        'month'          => $month,
                    ])->first();

                    if ($existing) {
                        $existing->update([
                            'target_qty'       => $monthlyQty,
                            'updated_by_uid'   => $uid,
                            'updated_datetime' => $now,
                        ]);
                    } else {
                        DistributorTarget::create([
                            'distributor_id'   => $distributor->id,
                            'product_id'       => $product->id,
                            'fiscal_year'      => $fiscalYear,
                            'month'            => $month,
                            'target_qty'       => $monthlyQty,
                            'created_by_uid'   => $uid,
                            'created_datetime' => $now,
                            'updated_by_uid'   => $uid,
                            'updated_datetime' => $now,
                        ]);
                    }
                }
                $success++;
            }
        });

        return response()->json([
            'success'     => $success,
            'fiscal_year' => $fiscalYear,
            'errors'      => $errors,
        ]);
    }

    /**
     * Import targets AND penjualan from the breakdown Excel export.
     * Columns: A=Distributor, B=Tahun Fiskal, C=Varietas, D=Satuan, E=Bulan, F=Target, G=Realisasi
     * - Col F (Target > 0)     → upsert distributor_targets
     * - Col G (Realisasi > 0)  → insert Sale (Type_Distributor) + SaleItem per month
     */
    public function importExcel(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls']);

        $monthMap = [
            'Apr' => 4, 'Mei' => 5, 'Jun' => 6,
            'Jul' => 7, 'Agu' => 8, 'Sep' => 9,
            'Okt' => 10, 'Nov' => 11, 'Des' => 12,
            'Jan' => 1,  'Feb' => 2,  'Mar' => 3,
        ];

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(
                $request->file('file')->getRealPath()
            );
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'errors' => ['Gagal membaca file: ' . $e->getMessage()]]);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        array_shift($rows); // remove header

        // Pre-load distributors + products for fast lookup
        $distributors = Customer::where('type', Customer::Type_Distributor)
            ->get(['id', 'name'])
            ->keyBy(fn($d) => strtolower(trim($d->name)));

        $products = Product::get(['id', 'name', 'uom_1', 'price_1'])
            ->keyBy(fn($p) => strtolower(trim($p->name)));

        $uid = auth()->id();
        $now = now();

        $targetCreated = 0;
        $targetUpdated = 0;
        $saleCreated   = 0;
        $errors        = [];

        DB::transaction(function () use (
            $rows, $monthMap, $distributors, $products,
            $uid, $now, &$targetCreated, &$targetUpdated, &$saleCreated, &$errors
        ) {
            foreach ($rows as $i => $row) {
                $rowNum = $i + 2; // 1-indexed + header

                [$distName, $fyRaw, $prodName, $satuan, $bulan, $targetQty, $realisasiQty] = array_pad(array_values($row), 7, null);

                $distName    = trim((string) $distName);
                $prodName    = trim((string) $prodName);
                $bulan       = trim((string) $bulan);
                $targetQty   = (float) ($targetQty ?? 0);
                $realisasiQty = (float) ($realisasiQty ?? 0);

                if ($distName === '' || $prodName === '' || $prodName === 'N/A' || $bulan === '') continue;
                if ($targetQty <= 0 && $realisasiQty <= 0) continue;

                // Parse fiscal year: "2025/2026" → 2025
                preg_match('/(\d{4})/', (string) $fyRaw, $fyM);
                $fiscalYear = isset($fyM[1]) ? (int) $fyM[1] : null;
                if (!$fiscalYear) { $errors[] = "Baris {$rowNum}: Tahun fiskal tidak valid ({$fyRaw})."; continue; }

                // Map month
                $month = $monthMap[$bulan] ?? null;
                if (!$month) { $errors[] = "Baris {$rowNum}: Bulan tidak dikenali ({$bulan})."; continue; }

                // Resolve distributor (exact → substring → keyword)
                $distKey     = strtolower($distName);
                $distributor = $distributors->get($distKey)
                    ?? $distributors->first(fn($d) => str_contains(strtolower($d->name), $distKey)
                                                   || str_contains($distKey, strtolower($d->name)))
                    ?? $distributors->first(function ($d) use ($distKey) {
                        $words = array_filter(preg_split('/\s+/', $distKey), fn($w) => strlen($w) > 3);
                        foreach ($words as $w) {
                            if (str_contains(strtolower($d->name), $w)) return true;
                        }
                        return false;
                    });
                if (!$distributor) { $errors[] = "Baris {$rowNum}: Distributor \"{$distName}\" tidak ditemukan."; continue; }

                // Resolve product (3-level fuzzy: exact → substring → keyword overlap)
                $product = $this->matchProduct($prodName, $products);
                if (!$product) { $errors[] = "Baris {$rowNum}: Produk \"{$prodName}\" tidak ditemukan."; continue; }

                // ── 1. Upsert TARGET ─────────────────────────────────────────
                if ($targetQty > 0) {
                    $existing = DistributorTarget::where([
                        'distributor_id' => $distributor->id,
                        'product_id'     => $product->id,
                        'fiscal_year'    => $fiscalYear,
                        'month'          => $month,
                    ])->first();

                    if ($existing) {
                        $existing->update(['target_qty' => $targetQty, 'updated_by_uid' => $uid, 'updated_datetime' => $now]);
                        $targetUpdated++;
                    } else {
                        DistributorTarget::create([
                            'distributor_id'   => $distributor->id,
                            'product_id'       => $product->id,
                            'fiscal_year'      => $fiscalYear,
                            'month'            => $month,
                            'target_qty'       => $targetQty,
                            'created_by_uid'   => $uid,
                            'created_datetime' => $now,
                            'updated_by_uid'   => $uid,
                            'updated_datetime' => $now,
                        ]);
                        $targetCreated++;
                    }
                }

                // ── 2. Insert PENJUALAN (realisasi) ──────────────────────────
                if ($realisasiQty > 0) {
                    $calYear = ($month >= 4) ? $fiscalYear : ($fiscalYear + 1);
                    $date    = Carbon::create($calYear, $month, 1)->toDateString();
                    $price   = (float) ($product->price_1 ?? 0);
                    $subtotal = round($realisasiQty * $price, 2);
                    $importNote = "Import Excel FY {$fiscalYear}/" . ($fiscalYear + 1);

                    // Skip if identical record already exists (idempotent)
                    $alreadyExists = Sale::where('sale_type', Sale::Type_Distributor)
                        ->where('distributor_id', $distributor->id)
                        ->where('date', $date)
                        ->where('notes', $importNote)
                        ->whereHas('items', fn($q) => $q->where('product_id', $product->id))
                        ->exists();

                    if (!$alreadyExists) {
                        $sale = Sale::create([
                            'sale_type'        => Sale::Type_Distributor,
                            'date'             => $date,
                            'distributor_id'   => $distributor->id,
                            'total_amount'     => $subtotal,
                            'notes'            => $importNote,
                            'created_by_uid'   => $uid,
                            'created_datetime' => $now,
                            'updated_by_uid'   => $uid,
                            'updated_datetime' => $now,
                        ]);

                        SaleItem::create([
                            'sale_id'    => $sale->id,
                            'product_id' => $product->id,
                            'quantity'   => $realisasiQty,
                            'unit'       => $satuan ?: ($product->uom_1 ?? 'kg'),
                            'price'      => $price,
                            'subtotal'   => $subtotal,
                        ]);

                        $saleCreated++;
                    }
                }
            }
        });

        return response()->json([
            'target_created' => $targetCreated,
            'target_updated' => $targetUpdated,
            'sale_created'   => $saleCreated,
            'errors'         => $errors,
        ]);
    }

    private function parseIndonesianNumber(string $s): float
    {
        // "16.000,00" → 16000.00
        $s = str_replace(['.', ' '], '', trim($s)); // remove thousand separators
        $s = str_replace(',', '.', $s);              // decimal comma → dot
        return (float) preg_replace('/[^0-9.]/', '', $s);
    }

    /**
     * Extract core keywords from a product name.
     * Strips: prefixes (ADV), suffixes (F1/F2/F3), pure numbers, very short words.
     * E.g. "ADV REVA" → ['reva'], "Beijing F1" → ['beijing'], "Hibrix 72 F1" → ['hibrix']
     */
    private function productKeywords(string $name): array
    {
        $skip = ['adv', 'f1', 'f2', 'f3', 'f4', 'kg', 'gr', 'ml', 'l'];
        $words = preg_split('/[\s\-\/]+/', strtolower(trim($name)));
        return array_values(array_filter($words, fn($w) =>
            strlen($w) > 2 && !in_array($w, $skip) && !is_numeric($w)
        ));
    }

    /** Match a product name against the products collection using 3-level fuzzy lookup. */
    private function matchProduct(string $prodName, \Illuminate\Support\Collection $products): ?object
    {
        $key = strtolower(trim($prodName));

        // 1. Exact match
        if ($p = $products->get($key)) return $p;

        // 2. Substring match
        $p = $products->first(fn($p) =>
            str_contains(strtolower($p->name), $key) ||
            str_contains($key, strtolower($p->name))
        );
        if ($p) return $p;

        // 3. Keyword overlap match (handles "Reva F1" vs "ADV REVA", "Beijing F1" vs "BEIJING 23")
        $excelWords = $this->productKeywords($prodName);
        if (empty($excelWords)) return null;

        return $products->first(function ($p) use ($excelWords) {
            $dbWords = $this->productKeywords($p->name);
            return !empty(array_intersect($excelWords, $dbWords));
        });
    }

    private function currentFiscalYear(): int
    {
        return now()->month >= 4 ? now()->year : now()->year - 1;
    }

    private function fiscalYearDateRange(int $fiscalYear): array
    {
        return [
            Carbon::create($fiscalYear, 4, 1)->startOfMonth()->toDateString(),
            Carbon::create($fiscalYear + 1, 3, 31)->endOfMonth()->toDateString(),
        ];
    }
}
