<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaActionRequest;
use App\Http\Requests\Admin\MediaUploadRequest;
use App\Models\Media;
use App\Services\Import\CommonsClient;
use App\Services\Import\ImportException;
use App\Services\Media\MediaLibrary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Photo manager on place/hotel pages and in the review queue (admin only).
 */
class MediaController extends Controller
{
    /** Morph types that have a photo gallery in the admin. */
    public const OWNER_TYPES = ['place', 'hotel', 'package'];

    public function __construct(private MediaLibrary $library) {}

    public function upload(MediaUploadRequest $request, string $type, int $id): RedirectResponse
    {
        $owner = $this->owner($type, $id);

        try {
            $this->library->upload($owner, $request->file('photo'), $request->meta());
        } catch (ImportException $e) {
            return $this->back()->with('error', $e->getMessage());
        }

        return $this->back()->with('status', 'Photo uploaded and published.');
    }

    public function url(MediaUploadRequest $request, string $type, int $id): RedirectResponse
    {
        $owner = $this->owner($type, $id);

        try {
            $this->library->importFromUrl($owner, $request->validated('url'), $request->meta());
        } catch (ImportException $e) {
            return $this->back()->withInput()->with('error', $e->getMessage());
        }

        return $this->back()->with('status', 'Photo imported from URL and published.');
    }

    /**
     * JSON search used by the "Search Wikimedia Commons" modal. The browser never calls Commons.
     */
    public function commonsSearch(MediaActionRequest $request, CommonsClient $commons): JsonResponse
    {
        try {
            $results = $commons->search($request->validated('q'), 30);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Commons search failed: '.$e->getMessage()], 502);
        }

        return response()->json([
            'results' => collect($results)->map(fn (array $c) => [
                'title' => $c['title'],
                'thumb' => $c['thumb_url'],
                'page' => $c['description_url'],
                'width' => $c['width'],
                'height' => $c['height'],
                'license' => $c['license'],
                'author' => $c['author'],
                'description' => $c['description'],
                'usable' => $c['rejected_because'] === null,
                'reason' => match ($c['rejected_because']) {
                    'license' => 'License not allowed',
                    'size' => 'Too small (under '.config('lankaguide.import.min_image_width').' px)',
                    'type' => 'Not a photo file',
                    'title' => 'Looks like a map, logo or diagram',
                    default => null,
                },
            ])->values(),
        ]);
    }

    public function commonsImport(MediaActionRequest $request, CommonsClient $commons, string $type, int $id): RedirectResponse
    {
        $owner = $this->owner($type, $id);
        $validated = $request->validated();

        $known = $owner->allMedia()->pluck('source_url')->filter()->all();
        $imported = 0;
        $skipped = [];

        try {
            foreach ($commons->files($validated['titles']) as $candidate) {
                // Only what the admin ticked, even if the API returns more.
                if (! in_array($candidate['title'], $validated['titles'], true)) {
                    continue;
                }

                if (in_array($candidate['description_url'], $known, true)) {
                    $skipped[] = "{$candidate['title']} (already imported or rejected before)";

                    continue;
                }

                if ($candidate['rejected_because'] !== null) {
                    $skipped[] = "{$candidate['title']} ({$candidate['rejected_because']})";

                    continue;
                }

                $this->library->storeCommons($owner, $candidate, MediaStatus::Published);
                $imported++;
            }
        } catch (Throwable $e) {
            return $this->back()->with('error', 'Commons import failed: '.$e->getMessage());
        }

        return $this->back()
            ->with('status', "{$imported} photo(s) imported from Wikimedia Commons with their credits.")
            ->with('warning', $skipped ? "Skipped:\n".implode("\n", $skipped) : null);
    }

    public function update(MediaActionRequest $request, Media $media): RedirectResponse
    {
        $media->forceFill($request->validated())->save();

        return back()->with('status', 'Photo details saved.');
    }

    public function cover(Media $media): RedirectResponse
    {
        $this->authorize('update', $media);

        $this->library->setCover($media);

        return back()->with('status', 'Cover photo changed.');
    }

    public function reorder(MediaActionRequest $request, string $type, int $id): JsonResponse
    {
        $owner = $this->owner($type, $id);

        $this->library->reorder($owner, $request->validated('ids'));

        return response()->json(['ok' => true]);
    }

    public function destroy(Media $media): RedirectResponse
    {
        $this->authorize('delete', $media);

        $this->library->reject($media);

        return back()->with('status', 'Photo removed. Commons photos are remembered so they are not imported again.');
    }

    /**
     * Bulk publish / reject from the review queue's Photos tab.
     */
    public function bulk(MediaActionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $photos = Media::whereKey($validated['ids'])->get();

        foreach ($photos as $media) {
            $validated['action'] === 'publish'
                ? $media->forceFill(['status' => MediaStatus::Published])->save()
                : $this->library->reject($media);
        }

        return back()->with('status', $photos->count().' photo(s) '.($validated['action'] === 'publish' ? 'published.' : 'removed.'));
    }

    private function owner(string $type, int $id): Model
    {
        $class = Relation::getMorphedModel($type);
        abort_unless(in_array($type, self::OWNER_TYPES, true) && $class !== null, 404);

        $owner = $class::findOrFail($id);
        $this->authorize('update', $owner);
        $this->authorize('create', Media::class);

        return $owner;
    }

    private function back(): RedirectResponse
    {
        return redirect()->to(url()->previous().'#photos');
    }
}
