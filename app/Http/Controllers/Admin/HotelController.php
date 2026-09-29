<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MealPlan;
use App\Enums\Tier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkActionRequest;
use App\Http\Requests\Admin\HotelRequest;
use App\Models\District;
use App\Models\Hotel;
use App\Services\Admin\PublishingService;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HotelController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Hotel::class);

        $hotels = Hotel::query()
            ->with('district')
            ->withCount(['roomRates', 'media'])
            ->when($request->query('q'), fn ($q, $search) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('town', 'like', "%{$search}%")))
            ->when($request->query('district'), fn ($q, $id) => $q->where('district_id', $id))
            ->when($request->query('tier'), fn ($q, $tier) => $q->tier($tier))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('status'), fn ($q, $status) => match ($status) {
                'published' => $q->published(),
                'draft' => $q->drafts()->where('is_active', true),
                'rejected' => $q->where('is_active', false),
                default => $q,
            })
            ->when($request->boolean('no_rates'), fn ($q) => $q->doesntHave('roomRates'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.hotels.index', [
            'hotels' => $hotels,
            'districts' => District::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Hotel::class);

        return view('admin.hotels.form', $this->formData(new Hotel(['tier' => Tier::Budget])));
    }

    public function store(HotelRequest $request): RedirectResponse
    {
        $hotel = new Hotel;
        $hotel->forceFill($request->validated())->save();

        return redirect()->route('admin.hotels.edit', $hotel)->with('status', "Hotel \"{$hotel->name}\" created. Add room rates and photos below.");
    }

    public function edit(Hotel $hotel): View
    {
        $this->authorize('view', $hotel);

        return view('admin.hotels.form', $this->formData($hotel));
    }

    public function update(HotelRequest $request, Hotel $hotel): RedirectResponse
    {
        $hotel->forceFill($request->validated())->save();

        return redirect()->route('admin.hotels.edit', $hotel)->with('status', 'Hotel saved.');
    }

    public function destroy(Hotel $hotel, MediaLibrary $media): RedirectResponse
    {
        $this->authorize('delete', $hotel);

        if (DB::table('trip_days')->where('hotel_id', $hotel->id)->exists()) {
            return back()->with('error', "\"{$hotel->name}\" is used in trip plans, so it can't be deleted. Unpublish or reject it instead.");
        }

        DB::transaction(function () use ($hotel, $media) {
            $media->deleteAllFor($hotel);
            $hotel->delete();
        });

        return redirect()->route('admin.hotels.index')->with('status', "Hotel \"{$hotel->name}\" deleted.");
    }

    public function bulk(BulkActionRequest $request, PublishingService $publishing): RedirectResponse
    {
        $result = $publishing->bulk($request->validated('action'), Hotel::whereKey($request->validated('ids'))->get());

        return back()
            ->with('status', "{$result['done']} hotel(s) updated.")
            ->with('warning', $result['blocked'] ? "Not published:\n".implode("\n", $result['blocked']) : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Hotel $hotel): array
    {
        return [
            'hotel' => $hotel->loadMissing(['roomRates', 'media']),
            'districts' => District::orderBy('name')->pluck('name', 'id'),
            'mealPlans' => collect(MealPlan::cases())->mapWithKeys(fn (MealPlan $plan) => [$plan->value => $plan->label()])->all(),
            'problems' => $hotel->exists ? app(PublishingService::class)->problems($hotel) : [],
        ];
    }
}
