<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DistributorStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DistributorStockController extends Controller
{
    public function __construct(private readonly StockService $stockService) {}

    public function index()
    {
        return inertia('admin/distributor-stock/Index', [
            'distributors' => Customer::where('type', Customer::Type_Distributor)
                ->where('active', true)->orderBy('name')->get(['id', 'name']),
            'products'     => Product::orderBy('name')->get(['id', 'name', 'unit']),
        ]);
    }

    public function data(Request $request)
    {
        $user   = Auth::user();
        $filter = $request->get('filter', []);

        $q = DistributorStock::with(['distributor:id,name', 'product:id,name,unit'])
            ->orderBy('stock_quantity', 'desc');

        if ($user->role === \App\Models\User::Role_Distributor && $user->customer_id) {
            $q->where('distributor_id', $user->customer_id);
        } elseif (!empty($filter['distributor_id'])) {
            $q->where('distributor_id', $filter['distributor_id']);
        }

        if (!empty($filter['product_id'])) {
            $q->where('product_id', $filter['product_id']);
        }

        return response()->json($q->paginate($request->get('per_page', 20))->withQueryString());
    }

    public function addStockPage()
    {
        return inertia('admin/distributor-stock/AddStock', [
            'distributors' => Customer::where('type', Customer::Type_Distributor)
                ->where('active', true)->orderBy('name')->get(['id', 'name']),
            'products'     => Product::orderBy('name')->get(['id', 'name', 'unit']),
        ]);
    }

    public function saveStock(Request $request)
    {
        $request->validate([
            'distributor_id' => 'required|exists:customers,id',
            'product_id'     => 'required|exists:products,id',
            'quantity'       => 'required|numeric|min:0.01',
            'reference'      => 'nullable|string|max:255',
            'notes'          => 'nullable|string|max:500',
        ]);

        $this->stockService->addStock(
            $request->distributor_id,
            $request->product_id,
            (float) $request->quantity,
            $request->reference ?? '',
            $request->notes ?? ''
        );

        return redirect(route('admin.distributor-stock.index'))
            ->with('success', 'Stok berhasil ditambahkan.');
    }

    public function movements($distributorId)
    {
        $distributor = Customer::where('type', Customer::Type_Distributor)->findOrFail($distributorId);

        return inertia('admin/distributor-stock/Movements', [
            'distributor' => $distributor,
            'products'    => Product::orderBy('name')->get(['id', 'name', 'unit']),
        ]);
    }

    public function movementsData(Request $request, $distributorId)
    {
        $q = StockMovement::with(['product:id,name,unit', 'created_by_user:id,username,name'])
            ->where('distributor_id', $distributorId);

        if ($pid = $request->get('product_id')) {
            $q->where('product_id', $pid);
        }
        if ($type = $request->get('type')) {
            $q->where('type', $type);
        }

        return response()->json(
            $q->orderByDesc('created_datetime')
              ->paginate($request->get('per_page', 20))
              ->withQueryString()
        );
    }
}
