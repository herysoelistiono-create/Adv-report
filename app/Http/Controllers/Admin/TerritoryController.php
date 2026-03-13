<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Province;
use App\Models\Village;
use Illuminate\Http\Request;

class TerritoryController extends Controller
{
    // --- Province ---

    public function provinceIndex()
    {
        return inertia('admin/territory/province/Index');
    }

    public function provinceData(Request $request)
    {
        $q = Province::query();
        if ($search = $request->get('search')) {
            $q->where('name', 'like', "%{$search}%");
        }
        return response()->json(
            $q->orderBy('name')->paginate($request->get('per_page', 20))->withQueryString()
        );
    }

    public function provinceSave(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $item = $request->id ? Province::findOrFail($request->id) : new Province();
        $item->name = $request->name;
        $item->save();
        return response()->json(['message' => 'Provinsi disimpan.', 'data' => $item]);
    }

    public function provinceDelete($id)
    {
        Province::findOrFail($id)->delete();
        return response()->json(['message' => 'Provinsi dihapus.']);
    }

    // --- District ---

    public function districtIndex()
    {
        return inertia('admin/territory/district/Index', [
            'provinces' => Province::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function districtData(Request $request)
    {
        $q = District::with('province:id,name');
        if ($pid = $request->get('province_id')) {
            $q->where('province_id', $pid);
        }
        if ($search = $request->get('search')) {
            $q->where('name', 'like', "%{$search}%");
        }
        return response()->json(
            $q->orderBy('name')->paginate($request->get('per_page', 20))->withQueryString()
        );
    }

    public function districtSave(Request $request)
    {
        $request->validate([
            'province_id' => 'required|exists:provinces,id',
            'name'        => 'required|string|max:255',
        ]);
        $item = $request->id ? District::findOrFail($request->id) : new District();
        $item->fill($request->only(['province_id', 'name']));
        $item->save();
        return response()->json(['message' => 'Kabupaten/Kota disimpan.', 'data' => $item]);
    }

    public function districtDelete($id)
    {
        District::findOrFail($id)->delete();
        return response()->json(['message' => 'Kabupaten/Kota dihapus.']);
    }

    // --- Village ---

    public function villageIndex()
    {
        return inertia('admin/territory/village/Index', [
            'provinces' => Province::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function villageData(Request $request)
    {
        $q = Village::with('district.province');
        if ($did = $request->get('district_id')) {
            $q->where('district_id', $did);
        }
        if ($search = $request->get('search')) {
            $q->where('name', 'like', "%{$search}%");
        }
        return response()->json(
            $q->orderBy('name')->paginate($request->get('per_page', 20))->withQueryString()
        );
    }

    public function villageSave(Request $request)
    {
        $request->validate([
            'district_id' => 'required|exists:districts,id',
            'name'        => 'required|string|max:255',
        ]);
        $item = $request->id ? Village::findOrFail($request->id) : new Village();
        $item->fill($request->only(['district_id', 'name']));
        $item->save();
        return response()->json(['message' => 'Desa/Kelurahan disimpan.', 'data' => $item]);
    }

    public function villageDelete($id)
    {
        Village::findOrFail($id)->delete();
        return response()->json(['message' => 'Desa/Kelurahan dihapus.']);
    }

    // --- JSON lookup endpoints for cascading dropdowns ---

    public function apiProvinces()
    {
        return response()->json(Province::orderBy('name')->get(['id', 'name']));
    }

    public function apiDistricts($provinceId)
    {
        return response()->json(
            District::where('province_id', $provinceId)->orderBy('name')->get(['id', 'name'])
        );
    }

    public function apiVillages($districtId)
    {
        return response()->json(
            Village::where('district_id', $districtId)->orderBy('name')->get(['id', 'name'])
        );
    }
}
