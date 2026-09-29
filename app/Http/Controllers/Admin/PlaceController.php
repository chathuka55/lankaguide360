<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkActionRequest;
use App\Http\Requests\Admin\PlaceRequest;
use App\Models\Category;
use App\Models\District;
use App\Models\ImportLog;
use App\Models\Place;
use App\Services\Admin\PublishingService;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlaceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Place::class);

        $places = Place::query()
            ->with(['district', 'categories', 'cover'])
            ->withCount('media')
            ->when($request->query('q'), fn ($q, $search) => $q->where('places.name', 'like', "%{$search}%"))
            ->when($request->query('district'), fn ($q, $id) => $q->where('district_id', $id))
            ->when($request->query('category'), fn ($q, $id) => $q->inCategories([(int) $id]))
            ->when($request->query('status'), fn ($q, $status) => match ($status) {
                'published' => $q->published(),
                'draft' => $q->drafts()->where('is_active', true),
                'rejected' => $q->where('is_active', false),
                default => $q,
            })
            ->when($request->query('source'), fn ($q, $source) => $q->where('source', $source))
            ->when($request->boolean('gems'), fn ($q) => $q->hiddenGems())
            ->when($request->boolean('no_coords'), fn ($q) => $q->whereNull('lat'))
            ->orderBy($request->query('sort') === 'updated' ? 'updated_at' : 'name', $request->query('sort') === 'updated' ? 'desc' : 'asc')
            ->paginate(25)
            ->withQueryString();

        return view('admin.places.index', [
            'places' => $places,
            'districts' => District::orderBy('name')->pluck('name', 'id'),
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Place::class);

        return view('admin.places.form', $this->formData(new Place));
    }

    public function store(PlaceRequest $request): RedirectResponse
    {
        $place = DB::transaction(function () use ($request) {
            $place = new Place;
            $place->forceFill([...$request->safe()->except('categories'), 'source' => 'manual'])->save();
            $place->categories()->sync($request->validated('categories', []));

            return $place;
        });

        return redirect()->route('admin.places.edit', $place)->with('status', "Place \"{$place->name}\" created. Add photos below.");
    }

    public function edit(Place $place): View
    {
        $this->authorize('view', $place);

        return view('admin.places.form', $this->formData($place));
    }

    public function update(PlaceRequest $request, Place $place): RedirectResponse
    {
        DB::transaction(function () use ($request, $place) {
            $place->forceFill($request->safe()->except('categories'))->save();
            $place->categories()->sync($request->validated('categories', []));
        });

        return redirect()->route('admin.places.edit', $place)->with('status', 'Place saved.');
    }

    public function destroy(Place $place, MediaLibrary $media): RedirectResponse
    {
        $this->authorize('delete', $place);

        if ($place->tripStops()->exists()) {
            return back()->with('error', "\"{$place->name}\" is used in trip plans, so it can't be deleted. Unpublish or reject it instead.");
        }

        DB::transaction(function () use ($place, $media) {
            $media->deleteAllFor($place);
            $place->delete();
        });

        return redirect()->route('admin.places.index')->with('status', "Place \"{$place->name}\" deleted.");
    }

    public function bulk(BulkActionRequest $request, PublishingService $publishing): RedirectResponse
    {
        $result = $publishing->bulk($request->validated('action'), Place::whereKey($request->validated('ids'))->get());

        return back()
            ->with('status', "{$result['done']} place(s) updated.")
            ->with('warning', $result['blocked'] ? "Not published:\n".implode("\n", $result['blocked']) : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Place $place): array
    {
        return [
            'place' => $place->loadMissing(['categories', 'media']),
            'districts' => District::orderBy('name')->pluck('name', 'id'),
            'categories' => Category::orderBy('name')->get(),
            'problems' => $place->exists ? app(PublishingService::class)->problems($place) : [],
            'logs' => $place->exists
                ? ImportLog::where('target_type', 'place')->where('target_id', $place->id)->latest('id')->limit(5)->get()
                : collect(),
        ];
    }
}
