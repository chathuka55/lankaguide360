<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Vehicle::class);

        return view('admin.vehicles.index', [
            'vehicles' => Vehicle::orderByRaw("FIELD(tier, 'budget', 'premium', 'luxury')")->orderBy('min_pax')->get()->groupBy(fn ($v) => $v->tier->value),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Vehicle::class);

        return view('admin.vehicles.form', ['vehicle' => new Vehicle]);
    }

    public function store(VehicleRequest $request): RedirectResponse
    {
        Vehicle::create($request->validated());

        return redirect()->route('admin.vehicles.index')->with('status', 'Vehicle added.');
    }

    public function edit(Vehicle $vehicle): View
    {
        $this->authorize('view', $vehicle);

        return view('admin.vehicles.form', ['vehicle' => $vehicle]);
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update($request->validated());

        return redirect()->route('admin.vehicles.index')->with('status', 'Vehicle saved.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('delete', $vehicle);

        if ($vehicle->trips()->exists()) {
            return back()->with('error', 'This vehicle is used in trip plans and can\'t be deleted.');
        }

        $vehicle->delete();

        return redirect()->route('admin.vehicles.index')->with('status', 'Vehicle deleted.');
    }
}
