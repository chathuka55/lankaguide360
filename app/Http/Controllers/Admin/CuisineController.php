<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CuisineRequest;
use App\Models\Cuisine;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Cuisine choices are short names, managed on one page.
 */
class CuisineController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Cuisine::class);

        return view('admin.cuisines.index', ['cuisines' => Cuisine::withCount('trips')->orderBy('name')->get()]);
    }

    public function store(CuisineRequest $request): RedirectResponse
    {
        Cuisine::create($request->validated());

        return back()->with('status', 'Cuisine added.');
    }

    public function update(CuisineRequest $request, Cuisine $cuisine): RedirectResponse
    {
        $cuisine->update($request->validated());

        return back()->with('status', 'Cuisine renamed.');
    }

    public function destroy(Cuisine $cuisine): RedirectResponse
    {
        $this->authorize('delete', $cuisine);

        $cuisine->trips()->detach();
        $cuisine->delete();

        return back()->with('status', "Cuisine \"{$cuisine->name}\" deleted.");
    }
}
