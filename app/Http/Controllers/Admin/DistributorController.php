<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Province;
use App\Models\Sale;
use Illuminate\Http\Request;

class DistributorController extends Controller
{
    public function index()
    {
        return inertia('admin/distributor/Index', [
            'provinces' => Province::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(Request $request)
    {
        $filter    = $request->get('filter', []);
        $orderBy   = $request->get('order_by', 'name');
        $orderType = $request->get('order_type', 'asc');

        $q = Customer::query()
            ->where('type', Customer::Type_Distributor)
            ->withCount(['sales as total_transactions'])
            ->withSum(['sales as total_sales'], 'total_amount')
            ->with(['province:id,name', 'district:id,name']);

        if (!empty($filter['search'])) {
            $q->where(function ($sq) use ($filter) {
                $sq->where('name', 'like', '%' . $filter['search'] . '%')
                    ->orWhere('phone', 'like', '%' . $filter['search'] . '%');
            });
        }
        if (!empty($filter['province_id'])) {
            $q->where('province_id', $filter['province_id']);
        }
        if (!empty($filter['status']) && in_array($filter['status'], ['active', 'inactive'])) {
            $q->where('active', $filter['status'] === 'active');
        }

        $q->orderBy($orderBy, $orderType);

        return response()->json($q->paginate($request->get('per_page', 10))->withQueryString());
    }

    public function detail($id)
    {
        $distributor = Customer::with([
            'province:id,name',
            'district:id,name',
            'village:id,name',
            'distributorStock.product:id,name,unit',
            'assigned_user:id,username,name',
        ])
            ->where('type', Customer::Type_Distributor)
            ->findOrFail($id);

        $recentSales = Sale::with(['retailer:id,name', 'items.product:id,name'])
            ->where('distributor_id', $id)
            ->orderByDesc('date')
            ->limit(10)
            ->get();

        return inertia('admin/distributor/Detail', [
            'data'         => $distributor,
            'recent_sales' => $recentSales,
        ]);
    }
}
