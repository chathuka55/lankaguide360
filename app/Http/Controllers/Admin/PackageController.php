<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PackageRequest;
use App\Models\Package;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Home page packages. Each wraps a template trip; building templates in the Trip Builder
 * ("Save as package") arrives in phase 12, so for now an existing trip is chosen here.
 */
class PackageController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Package::class);

        return view('admin.packages.index', ['packages' => Package::with('templateTrip')->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        $this->authorize('create', Package::class);

        return view('admin.packages.form', ['package' => new Package(['sort_order' => 0]), 'trips' => $this->tripOptions()]);
    }

    public function store(PackageRequest $request): RedirectResponse
    {
        Package::create([...$request->validated(), 'sort_order' => $request->validated('sort_order') ?? 0]);

        return redirect()->route('admin.packages.index')->with('status', 'Package created.');
    }

    public function edit(Package $package): View
    {
        $this->authorize('view', $package);

        return view('admin.packages.form', ['package' => $package, 'trips' => $this->tripOptions()]);
    }

    public function update(PackageRequest $request, Package $package): RedirectResponse
    {
        $package->update([...$request->validated(), 'sort_order' => $request->validated('sort_order') ?? 0]);

        return redirect()->route('admin.packages.index')->with('status', 'Package saved.');
    }

    public function destroy(Package $package): RedirectResponse
    {
        $this->authorize('delete', $package);

        $package->delete();

        return redirect()->route('admin.packages.index')->with('status', "Package \"{$package->name}\" deleted.");
    }

    /**
     * @return array<int, string>
     */
    private function tripOptions(): array
    {
        return Trip::latest('id')->limit(200)->get(['id', 'reference', 'tier', 'days'])
            ->mapWithKeys(fn (Trip $trip) => [$trip->id => "{$trip->reference} · {$trip->days} days · {$trip->tier->label()}"])
            ->all();
    }
}
