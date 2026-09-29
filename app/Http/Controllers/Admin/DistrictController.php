<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DistrictRequest;
use App\Models\Category;
use App\Models\District;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The 25 districts are fixed administrative areas: they can be edited, not created or deleted.
 */
class DistrictController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', District::class);

        return view('admin.districts.index', [
            'districts' => District::with(['province', 'categories'])
                ->withCount(['places', 'hotels'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function edit(District $district): View
    {
        $this->authorize('view', $district);

        return view('admin.districts.form', [
            'district' => $district->load('categories', 'province'),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(DistrictRequest $request, District $district): RedirectResponse
    {
        $district->forceFill($request->safe()->except('categories'))->save();
        $district->categories()->sync($request->validated('categories', []));

        return redirect()->route('admin.districts.edit', $district)->with('status', 'District saved.');
    }
}
