<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\District;
use App\Models\Product;
use App\Models\Province;
use App\Models\Sale;
use App\Models\Village;
use App\Services\SaleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $saleService) {}

    public function index()
    {
        $user = Auth::user();

        $distributorsQuery = Customer::where('type', Customer::Type_Distributor)
            ->where('active', true)
            ->orderBy('name');

        if ($user->role === \App\Models\User::Role_Distributor && $user->customer_id) {
            $distributorsQuery->where('id', $user->customer_id);
        }

        $retailersQuery = Customer::whereIn('type', [Customer::Type_R1, Customer::Type_R2])
            ->where('active', true)
            ->orderBy('name');

        if ($user->role === \App\Models\User::Role_BS || $user->role === \App\Models\User::Role_FieldOfficer) {
            $retailersQuery->where('created_by_uid', $user->id);
        }

        return inertia('admin/sale/Index', [
            'distributors' => $distributorsQuery->get(['id', 'name']),
            'retailers'    => $retailersQuery->get(['id', 'name', 'type']),
        ]);
    }

    public function data(Request $request)
    {
        $user   = Auth::user();
        $filter = $request->get('filter', []);

        $q = Sale::with([
            'distributor:id,name',
            'retailer:id,name',
            'province:id,name',
            'district:id,name',
            'created_by_user:id,username,name',
        ]);

        // Role-based scoping
        if ($user->role === \App\Models\User::Role_BS || $user->role === \App\Models\User::Role_FieldOfficer) {
            // BS/Field Officer can only see retailer-type sales they created
            $q->where('sale_type', Sale::Type_Retailer)
              ->where('created_by_uid', $user->id);
        } elseif ($user->role === \App\Models\User::Role_Agronomist) {
            // Agronomist sees ALL sales (distributor-type they input + retailer-type by BS)
        } elseif ($user->role === \App\Models\User::Role_Distributor && $user->customer_id) {
            // Distributor role: hanya lihat penjualan mereka sendiri
            $q->where('distributor_id', $user->customer_id);
        }

        if (!empty($filter['distributor_id'])) {
            $q->where('distributor_id', $filter['distributor_id']);
        }
        if (!empty($filter['retailer_id'])) {
            $q->where('retailer_id', $filter['retailer_id']);
        }
        // ── Fiscal year + month filter ────────────────────────────────────────
        if (!empty($filter['fiscal_year'])) {
            $fy = (int) $filter['fiscal_year'];
            if (!empty($filter['month'])) {
                $m       = (int) $filter['month'];
                $calYear = $m >= 4 ? $fy : $fy + 1;
                $start   = \Carbon\Carbon::create($calYear, $m, 1)->startOfMonth()->toDateString();
                $end     = \Carbon\Carbon::create($calYear, $m, 1)->endOfMonth()->toDateString();
                $q->whereBetween('date', [$start, $end]);
            } else {
                $q->whereBetween('date', [
                    "{$fy}-04-01",
                    ($fy + 1) . "-03-31",
                ]);
            }
        } elseif (!empty($filter['start_date'])) {
            $q->whereDate('date', '>=', $filter['start_date']);
            if (!empty($filter['end_date'])) {
                $q->whereDate('date', '<=', $filter['end_date']);
            }
        }
        if (!empty($filter['search'])) {
            $q->where(function ($sq) use ($filter) {
                $sq->whereHas('distributor', fn($dq) =>
                    $dq->where('name', 'like', '%' . $filter['search'] . '%')
                )->orWhereHas('retailer', fn($rq) =>
                    $rq->where('name', 'like', '%' . $filter['search'] . '%')
                );
            });
        }

        $orderBy   = $request->get('order_by', 'date');
        $orderType = $request->get('order_type', 'desc');
        $q->orderBy($orderBy, $orderType);

        $totalSalesSum = (clone $q)->sum('total_amount');

        $paginated = $q->paginate($request->get('per_page', 10))->withQueryString();

        return response()->json(array_merge($paginated->toArray(), [
            'total_sales_sum' => (float) $totalSalesSum,
        ]));
    }

    public function detail($id)
    {
        $sale = Sale::with([
            'distributor', 'retailer', 'province', 'district', 'village',
            'items.product', 'created_by_user:id,username,name', 'updated_by_user:id,username,name',
        ])->findOrFail($id);

        return inertia('admin/sale/Detail', ['data' => $sale]);
    }

    public function editor($id = 0)
    {
        $user = Auth::user();
        $sale = $id ? Sale::with('items')->findOrFail($id) : null;

        // Determine sale type based on user role
        $saleType = Sale::Type_Distributor; // default
        if ($user->role === \App\Models\User::Role_BS || $user->role === \App\Models\User::Role_FieldOfficer) {
            $saleType = Sale::Type_Retailer;
        }
        // If editing existing sale, use its type
        if ($sale) {
            $saleType = $sale->sale_type ?? $saleType;
        }

        $distributors = Customer::where('type', Customer::Type_Distributor)
            ->where('active', true)->orderBy('name')->get(['id', 'name']);

        // R1 and R2 customers:
        // - For BS: only show customers they registered (their own kios)
        // - For others: show all
        $retailersQuery = Customer::whereIn('type', [Customer::Type_R1, Customer::Type_R2])
            ->where('active', true)->orderBy('name');

        if ($user->role === \App\Models\User::Role_BS || $user->role === \App\Models\User::Role_FieldOfficer) {
            $retailersQuery->where('created_by_uid', $user->id);
        }

        $retailers = $retailersQuery->get(['id', 'name', 'type']);

        // Distributor user: default to their own customer record
        $defaultDistributorId = null;
        if ($user->role === \App\Models\User::Role_Distributor && $user->customer_id) {
            $defaultDistributorId = $user->customer_id;
        }

        return inertia('admin/sale/Editor', [
            'data'                 => $sale,
            'saleType'             => $saleType,
            'distributors'         => $distributors,
            'retailers'            => $retailers,
            'products'             => Product::orderBy('name')->get(['id', 'name', 'uom_1', 'uom_2', 'price_1', 'price_2']),
            'provinces'            => Province::orderBy('name')->get(['id', 'name']),
            'defaultDistributorId' => $defaultDistributorId,
        ]);
    }

    public function save(Request $request)
    {
        $saleType = $request->input('sale_type', Sale::Type_Distributor);

        $rules = [
            'sale_type'      => 'required|in:distributor,retailer',
            'date'           => 'required|date',
            'distributor_id' => 'required|exists:customers,id',
            'retailer_id'    => $saleType === Sale::Type_Retailer ? 'required|exists:customers,id' : 'nullable|exists:customers,id',
            'province_id'    => 'nullable|exists:provinces,id',
            'district_id'    => 'nullable|exists:districts,id',
            'village_id'     => 'nullable|exists:villages,id',
            'notes'          => 'nullable|string|max:500',
            'items'          => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.01',
            'items.*.price'      => 'required|numeric|min:0',
        ];

        $request->validate($rules);

        $data  = $request->only(['sale_type', 'date', 'distributor_id', 'retailer_id', 'province_id', 'district_id', 'village_id', 'notes']);
        $items = $request->get('items');

        if ($request->id) {
            $sale = Sale::findOrFail($request->id);
            $sale = $this->saleService->update($sale, $data, $items);
            $message = 'Penjualan berhasil diperbarui.';
        } else {
            $sale = $this->saleService->create($data, $items);
            $message = 'Penjualan berhasil disimpan.';
        }

        return redirect(route('admin.sale.detail', $sale->id))->with('success', $message);
    }

    public function delete($id)
    {
        $sale = Sale::with('items')->findOrFail($id);
        $this->saleService->delete($sale);

        return response()->json(['message' => 'Penjualan berhasil dihapus.']);
    }

    public function importTemplate(Request $request): StreamedResponse
    {
        // Optional: pre-fill for a specific distributor
        $selectedDistributor = $request->get('distributor_id')
            ? Customer::find($request->get('distributor_id'))
            : null;

        $distName = $selectedDistributor?->name ?? 'Nama Distributor';

        $spreadsheet = new Spreadsheet();

        // ── Sheet 1: Data Import ─────────────────────────────────────────────
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Data Import');

        $headers = ['Tanggal', 'Jenis', 'Distributor', 'R1/R2', 'Produk', 'Qty', 'Satuan', 'Harga', 'Catatan'];
        $sheet->fromArray($headers, null, 'A1');

        $sheet->getStyle('A1:I1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1976D2']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Sample rows (pre-filled with selected distributor name if provided)
        $today = Carbon::now()->format('Y-m-d');
        $sheet->fromArray([$today, 'distributor', $distName, '',                'Nama Produk', 100, 'kg',  50000, ''],        null, 'A2');
        $sheet->fromArray([$today, 'retailer',    $distName, 'Nama R1 atau R2', 'Nama Produk',  50, 'kg',  55000, 'Opsional'], null, 'A3');

        $sheet->getStyle('A2:I3')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF7FF']],
        ]);

        // Notes row
        $notes = ['Format: YYYY-MM-DD', 'distributor/retailer', 'Wajib', 'Wajib jika retailer', 'Wajib', 'Angka > 0', 'kg, pcs, dll', 'Per unit', 'Opsional'];
        $sheet->fromArray($notes, null, 'A4');
        $sheet->getStyle('A4:I4')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '888888']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        // ── Sheet 2: Referensi ───────────────────────────────────────────────
        $ref = $spreadsheet->createSheet()->setTitle('Referensi');

        foreach (['A1' => 'Daftar Distributor', 'C1' => 'Daftar Produk', 'D1' => 'Satuan'] as $cell => $label) {
            $ref->setCellValue($cell, $label);
        }
        $ref->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3F2FD']],
        ]);

        // If a distributor is selected, show only that one; otherwise show all
        $distQuery = Customer::where('type', Customer::Type_Distributor)->orderBy('name');
        if ($selectedDistributor) {
            $distQuery->where('id', $selectedDistributor->id);
        }
        $distributors = $distQuery->pluck('name');
        foreach ($distributors as $i => $name) {
            $ref->setCellValue('A' . ($i + 2), $name);
        }

        $products = Product::orderBy('name')->get(['name', 'uom_1', 'uom_2']);
        foreach ($products as $i => $product) {
            $ref->setCellValue('C' . ($i + 2), $product->name);
            $satuan = array_filter([$product->uom_1, $product->uom_2]);
            $ref->setCellValue('D' . ($i + 2), implode(' / ', $satuan));
        }

        foreach (['A', 'C', 'D'] as $col) {
            $ref->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = $selectedDistributor
            ? 'template_import_' . \Str::slug($selectedDistributor->name) . '.xlsx'
            : 'template_import_penjualan.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function import(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls']);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getRealPath());
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'errors' => ['File tidak dapat dibaca: ' . $e->getMessage()]], 422);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        array_shift($rows); // remove header

        $errors  = [];
        $success = 0;

        foreach ($rows as $rowIndex => $row) {
            $rowNum = $rowIndex + 2;

            $date            = trim((string) ($row['A'] ?? ''));
            $jenis           = strtolower(trim((string) ($row['B'] ?? '')));
            $distributorName = trim((string) ($row['C'] ?? ''));
            $retailerName    = trim((string) ($row['D'] ?? ''));
            $productName     = trim((string) ($row['E'] ?? ''));
            $qty             = (float) ($row['F'] ?? 0);
            $unit            = trim((string) ($row['G'] ?? ''));
            $price           = (float) ($row['H'] ?? 0);
            $notes           = trim((string) ($row['I'] ?? ''));

            // Skip blank rows and the notes hint row
            if (empty($date) && empty($distributorName) && empty($productName)) continue;

            if (empty($date)) { $errors[] = "Baris {$rowNum}: Tanggal kosong"; continue; }
            if (!in_array($jenis, [Sale::Type_Distributor, Sale::Type_Retailer])) {
                $errors[] = "Baris {$rowNum}: Jenis harus 'distributor' atau 'retailer' (nilai: '{$jenis}')";
                continue;
            }
            if (empty($distributorName)) { $errors[] = "Baris {$rowNum}: Kolom Distributor kosong"; continue; }
            if (empty($productName))     { $errors[] = "Baris {$rowNum}: Kolom Produk kosong"; continue; }
            if ($qty <= 0)               { $errors[] = "Baris {$rowNum}: Qty harus lebih dari 0"; continue; }

            // Parse date (Excel serial or string)
            try {
                $saleDate = is_numeric($date)
                    ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $date)->format('Y-m-d')
                    : Carbon::parse($date)->format('Y-m-d');
            } catch (\Exception $e) {
                $errors[] = "Baris {$rowNum}: Format tanggal tidak valid '{$date}'";
                continue;
            }

            $distributor = Customer::where('type', Customer::Type_Distributor)
                ->whereRaw('LOWER(name) = LOWER(?)', [$distributorName])->first();
            if (!$distributor) { $errors[] = "Baris {$rowNum}: Distributor '{$distributorName}' tidak ditemukan"; continue; }

            $retailerId = null;
            if ($jenis === Sale::Type_Retailer) {
                if (empty($retailerName)) { $errors[] = "Baris {$rowNum}: R1/R2 wajib diisi untuk jenis 'retailer'"; continue; }
                $retailer = Customer::whereIn('type', [Customer::Type_R1, Customer::Type_R2])
                    ->whereRaw('LOWER(name) = LOWER(?)', [$retailerName])->first();
                if (!$retailer) { $errors[] = "Baris {$rowNum}: R1/R2 '{$retailerName}' tidak ditemukan"; continue; }
                $retailerId = $retailer->id;
            }

            $product = Product::whereRaw('LOWER(name) = LOWER(?)', [$productName])->first();
            if (!$product) { $errors[] = "Baris {$rowNum}: Produk '{$productName}' tidak ditemukan"; continue; }

            if (empty($unit)) {
                $unit = $product->uom_1 ?: $product->uom_2 ?: '';
            }

            try {
                $this->saleService->create(
                    ['sale_type' => $jenis, 'date' => $saleDate, 'distributor_id' => $distributor->id, 'retailer_id' => $retailerId, 'notes' => $notes ?: null],
                    [['product_id' => $product->id, 'quantity' => $qty, 'unit' => $unit, 'price' => $price, 'subtotal' => round($qty * $price, 2)]]
                );
                $success++;
            } catch (\Exception $e) {
                $errors[] = "Baris {$rowNum}: Gagal - " . $e->getMessage();
            }
        }

        return response()->json(['success' => $success, 'errors' => $errors]);
    }

    public function export(Request $request)
    {
        $filter = $request->get('filter', []);

        $q = Sale::with(['distributor:id,name', 'retailer:id,name', 'province:id,name', 'items.product:id,name']);
        if (!empty($filter['start_date'])) $q->whereDate('date', '>=', $filter['start_date']);
        if (!empty($filter['end_date']))   $q->whereDate('date', '<=', $filter['end_date']);
        if (!empty($filter['province_id'])) $q->where('province_id', $filter['province_id']);
        $items = $q->orderBy('date', 'desc')->get();

        $title    = 'Laporan Penjualan';
        $filename = $title . ' - ' . env('APP_NAME') . ' - ' . Carbon::now()->format('dmY_His');

        if ($request->get('format') === 'pdf') {
            $pdf = Pdf::loadView('reports.sales-by-region', compact('items', 'title', 'filter'))
                ->setPaper('a4', 'landscape');
            return $pdf->download($filename . '.pdf');
        }

        // Excel export
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['No', 'Tanggal', 'Distributor', 'Retailer', 'Provinsi', 'Total (Rp)'], null, 'A1');
        $row = 2;
        foreach ($items as $i => $sale) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $sale->date->format('d/m/Y'));
            $sheet->setCellValue("C{$row}", $sale->distributor?->name ?? '-');
            $sheet->setCellValue("D{$row}", $sale->retailer?->name ?? '-');
            $sheet->setCellValue("E{$row}", $sale->province?->name ?? '-');
            $sheet->setCellValue("F{$row}", (float) $sale->total_amount);
            $row++;
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}.xlsx\"");
        return $response;
    }
}
