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
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $saleService) {}

    public function index()
    {
        return inertia('admin/sale/Index');
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
            // Agronomist sees distributor-type sales
            $q->where('sale_type', Sale::Type_Distributor);
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
        if (!empty($filter['province_id'])) {
            $q->where('province_id', $filter['province_id']);
        }
        if (!empty($filter['start_date'])) {
            $q->whereDate('date', '>=', $filter['start_date']);
        }
        if (!empty($filter['end_date'])) {
            $q->whereDate('date', '<=', $filter['end_date']);
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

        return response()->json($q->paginate($request->get('per_page', 10))->withQueryString());
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

        // R1 and R2 customers — used as buyer in distributor sales, seller in retailer sales
        $retailers = Customer::whereIn('type', [Customer::Type_R1, Customer::Type_R2])
            ->where('active', true)->orderBy('name')->get(['id', 'name', 'type']);

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
            'products'             => Product::orderBy('name')->get(['id', 'name', 'uom_1', 'uom_2']),
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
