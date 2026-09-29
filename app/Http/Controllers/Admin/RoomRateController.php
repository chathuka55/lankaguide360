<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomRateRequest;
use App\Models\Hotel;
use App\Models\RoomRate;
use Illuminate\Http\RedirectResponse;

/**
 * Room rates are edited inline on the hotel page.
 */
class RoomRateController extends Controller
{
    public function store(RoomRateRequest $request, Hotel $hotel): RedirectResponse
    {
        $hotel->roomRates()->create($request->rate());

        return redirect()->to(route('admin.hotels.edit', $hotel).'#rates')->with('status', 'Room rate added.');
    }

    public function update(RoomRateRequest $request, RoomRate $rate): RedirectResponse
    {
        $rate->update($request->rate());

        return redirect()->to(route('admin.hotels.edit', $rate->hotel).'#rates')->with('status', 'Room rate saved.');
    }

    public function destroy(RoomRate $rate): RedirectResponse
    {
        $this->authorize('update', $rate->hotel);

        $hotel = $rate->hotel;
        $rate->delete();

        return redirect()->to(route('admin.hotels.edit', $hotel).'#rates')->with('status', 'Room rate deleted.');
    }
}
