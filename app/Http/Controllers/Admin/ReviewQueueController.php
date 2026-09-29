<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaStatus;
use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Hotel;
use App\Models\ImportLog;
use App\Models\Media;
use App\Models\Place;
use App\Services\Admin\PublishingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "Imported content": everything importers created that is still a draft, side by side with
 * its source and license, so an admin can publish, edit or reject it. Actions post to the
 * places/hotels/media bulk routes so the same publishing rules apply everywhere.
 */
class ReviewQueueController extends Controller
{
    public const TABS = ['places', 'hotels', 'photos'];

    public function index(Request $request, PublishingService $publishing): View
    {
        Gate::authorize('admin');

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'places';

        $data = [
            'tab' => $tab,
            'counts' => [
                'places' => Place::drafts()->where('is_active', true)->count(),
                'hotels' => Hotel::drafts()->where('is_active', true)->count(),
                'photos' => Media::where('status', MediaStatus::Draft->value)->count(),
            ],
            'districts' => District::orderBy('name')->pluck('name', 'id'),
            'publishing' => $publishing,
        ];

        return view('admin.review.index', $data + match ($tab) {
            'places' => $this->places($request),
            'hotels' => $this->hotels($request),
            'photos' => $this->photos($request),
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function places(Request $request): array
    {
        $places = Place::drafts()
            ->where('is_active', true)
            ->with(['district', 'categories', 'cover'])
            ->withCount(['media as draft_photos_count' => fn ($q) => $q->where('status', MediaStatus::Draft->value)])
            ->when($request->query('district'), fn ($q, $id) => $q->where('district_id', $id))
            ->when($request->query('source'), fn ($q, $source) => $q->where('source', $source))
            ->when($request->query('ready') === '1', fn ($q) => $q->whereNotNull('lat')->whereHas('categories'))
            ->when($request->query('ready') === '0', fn ($q) => $q->where(fn ($w) => $w->whereNull('lat')->orWhereDoesntHave('categories')))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Places whose latest Wikipedia import said "not found" or "disambiguation".
        $attention = ImportLog::where('importer', 'wikipedia')
            ->where('target_type', 'place')
            ->whereIn('status', ['not_found', 'needs_review', 'updated', 'skipped'])
            ->latest('id')
            ->limit(1000)
            ->get()
            ->unique('target_id')
            ->whereIn('status', ['not_found', 'needs_review'])
            ->values();

        $attentionPlaces = Place::whereKey($attention->pluck('target_id'))->get()->keyBy('id');

        return [
            'places' => $places,
            'attention' => $attention->filter(fn ($log) => $attentionPlaces->has($log->target_id))
                ->map(fn ($log) => ['log' => $log, 'place' => $attentionPlaces[$log->target_id]]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function hotels(Request $request): array
    {
        return [
            'hotels' => Hotel::drafts()
                ->where('is_active', true)
                ->with('district')
                ->when($request->query('district'), fn ($q, $id) => $q->where('district_id', $id))
                ->when($request->query('tier'), fn ($q, $tier) => $q->tier($tier))
                ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
                ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('town', 'like', "%{$s}%")))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function photos(Request $request): array
    {
        return [
            'photos' => Media::where('status', MediaStatus::Draft->value)
                ->with('mediable')
                ->when($request->query('type'), fn ($q, $type) => $q->where('mediable_type', $type))
                ->orderBy('mediable_type')
                ->orderBy('mediable_id')
                ->orderBy('sort_order')
                ->paginate(48)
                ->withQueryString(),
        ];
    }
}
