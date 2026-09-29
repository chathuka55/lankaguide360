<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuideRequest;
use App\Models\Guide;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GuideController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Guide::class);

        return view('admin.guides.index', ['guides' => Guide::orderBy('type')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        $this->authorize('create', Guide::class);

        return view('admin.guides.form', ['guide' => new Guide(['is_available' => true, 'languages' => ['English']])]);
    }

    public function store(GuideRequest $request): RedirectResponse
    {
        Guide::create($request->validated());

        return redirect()->route('admin.guides.index')->with('status', 'Guide added.');
    }

    public function edit(Guide $guide): View
    {
        $this->authorize('view', $guide);

        return view('admin.guides.form', ['guide' => $guide]);
    }

    public function update(GuideRequest $request, Guide $guide): RedirectResponse
    {
        $guide->update($request->validated());

        return redirect()->route('admin.guides.index')->with('status', 'Guide saved.');
    }

    public function destroy(Guide $guide): RedirectResponse
    {
        $this->authorize('delete', $guide);

        if ($guide->trips()->exists()) {
            return back()->with('error', 'This guide is assigned to trips. Mark them unavailable instead.');
        }

        $guide->delete();

        return redirect()->route('admin.guides.index')->with('status', 'Guide deleted.');
    }
}
