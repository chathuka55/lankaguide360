<?php

namespace App\Services\Media;

use App\Enums\MediaSource;
use App\Enums\MediaStatus;
use App\Models\Media;
use App\Services\Import\ImportClient;
use App\Services\Import\ImportException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Everything that creates, orders or removes photos for places, hotels and packages.
 * Every photo is stored as WebP variants and records who owns it and under which license.
 */
class MediaLibrary
{
    public function __construct(
        private ImageProcessor $images,
        private ImportClient $client,
    ) {}

    /**
     * Store a Commons candidate (from CommonsClient) for $owner.
     *
     * @param  array<string, mixed>  $candidate
     */
    public function storeCommons(Model $owner, array $candidate, MediaStatus $status): Media
    {
        $binary = $this->client->download($candidate['thumb_url'], [
            'group' => 'download',
            'delay_ms' => (int) config('lankaguide.import.delay_ms.download'),
        ]);

        $description = (string) ($candidate['description'] ?? '');

        return $this->create($owner, $binary, substr(sha1($candidate['title']), 0, 8), [
            'alt' => Str::limit($description !== '' ? $description : $this->ownerName($owner), 250),
            // Str::limit appends "...", so leave room for it inside the 500-character column.
            'caption' => $description !== '' ? Str::limit($description, 497) : null,
            'source' => MediaSource::Commons,
            'source_url' => $candidate['description_url'],
            'author' => Str::limit($candidate['author'] ?: 'Unknown author', 250),
            'license' => $candidate['license'],
            'license_url' => $candidate['license_url'],
            'status' => $status,
        ]);
    }

    /**
     * An admin upload: owner/credit and license are required (CLAUDE.md data rules).
     *
     * @param  array{alt: string, author: string, license: string, license_url?: string|null, caption?: string|null}  $meta
     */
    public function upload(Model $owner, UploadedFile $file, array $meta): Media
    {
        return $this->create($owner, $file->getContent(), Str::random(8), [
            ...$this->metaAttributes($meta),
            'source' => MediaSource::Upload,
            'source_url' => null,
            'status' => MediaStatus::Published,
        ]);
    }

    /**
     * Download an image the admin has the right to use.
     *
     * @param  array{alt: string, author: string, license: string, license_url?: string|null, caption?: string|null}  $meta
     */
    public function importFromUrl(Model $owner, string $url, array $meta): Media
    {
        self::assertPublicUrl($url);

        $binary = $this->client->download($url, ['group' => 'download']);

        if (strlen($binary) > 15 * 1024 * 1024) {
            throw new ImportException('The image is larger than 15 MB.');
        }

        return $this->create($owner, $binary, Str::random(8), [
            ...$this->metaAttributes($meta),
            'source' => MediaSource::Url,
            'source_url' => $url,
            'status' => MediaStatus::Published,
        ]);
    }

    /**
     * Remove a photo from the gallery. Commons photos keep their row as "rejected" so the
     * importer never fetches them again; uploads are deleted completely.
     */
    public function reject(Media $media): void
    {
        DB::transaction(function () use ($media) {
            $this->deleteFiles($media);
            $wasCover = $media->is_cover;
            $owner = $media->mediable;

            if ($media->source === MediaSource::Commons) {
                $media->forceFill(['status' => MediaStatus::Rejected, 'is_cover' => false, 'variants' => null])->save();
            } else {
                $media->delete();
            }

            if ($wasCover && $owner) {
                $next = $owner->media()->first();
                $next?->forceFill(['is_cover' => true])->save();
            }
        });
    }

    /**
     * Delete every photo (rows and files) of a record that is being deleted.
     */
    public function deleteAllFor(Model $owner): void
    {
        Media::where('mediable_type', $owner->getMorphClass())
            ->where('mediable_id', $owner->getKey())
            ->get()
            ->each(function (Media $media) {
                $this->deleteFiles($media);
                $media->delete();
            });
    }

    public function setCover(Media $media): void
    {
        DB::transaction(function () use ($media) {
            Media::where('mediable_type', $media->mediable_type)
                ->where('mediable_id', $media->mediable_id)
                ->update(['is_cover' => false]);

            $media->forceFill(['is_cover' => true])->save();
        });
    }

    /**
     * @param  array<int, int|string>  $ids  media ids in the new order
     */
    public function reorder(Model $owner, array $ids): void
    {
        DB::transaction(function () use ($owner, $ids) {
            foreach (array_values($ids) as $position => $id) {
                Media::where('mediable_type', $owner->getMorphClass())
                    ->where('mediable_id', $owner->getKey())
                    ->whereKey($id)
                    ->update(['sort_order' => $position + 1]);
            }
        });
    }

    /**
     * Block downloads from local or private networks (the server must not fetch internal URLs).
     */
    public static function assertPublicUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new ImportException('Only http(s) image URLs can be imported.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            throw new ImportException("Could not resolve {$host}.");
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new ImportException('Images from local or private network addresses are not allowed.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function create(Model $owner, string $binary, string $suffix, array $attributes): Media
    {
        $directory = 'media/'.$owner->getMorphClass().'/'.$owner->getKey();
        $basename = Str::limit(Str::slug($owner->slug ?? $this->ownerName($owner)), 80, '').'-'.$suffix;

        try {
            $stored = $this->images->storeVariants($binary, $directory, $basename);
        } catch (\Throwable $e) {
            throw new ImportException('The file could not be read as an image.', previous: $e);
        }

        $hasCover = Media::where('mediable_type', $owner->getMorphClass())
            ->where('mediable_id', $owner->getKey())
            ->where('is_cover', true)
            ->where('status', '!=', MediaStatus::Rejected->value)
            ->exists();

        $nextOrder = (int) Media::where('mediable_type', $owner->getMorphClass())
            ->where('mediable_id', $owner->getKey())
            ->max('sort_order') + 1;

        return Media::create([
            'mediable_type' => $owner->getMorphClass(),
            'mediable_id' => $owner->getKey(),
            'path' => $stored['path'],
            'variants' => $stored['variants'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'is_cover' => ! $hasCover,
            'sort_order' => $nextOrder,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function metaAttributes(array $meta): array
    {
        return [
            'alt' => Str::limit($meta['alt'], 250),
            'caption' => filled($meta['caption'] ?? null) ? Str::limit($meta['caption'], 497) : null,
            'author' => Str::limit($meta['author'], 250),
            'license' => Str::limit($meta['license'], 97),
            'license_url' => $meta['license_url'] ?? null,
        ];
    }

    private function deleteFiles(Media $media): void
    {
        $paths = array_values(array_unique(array_filter([$media->path, ...array_values($media->variants ?? [])])));

        Storage::disk('public')->delete($paths);
    }

    private function ownerName(Model $owner): string
    {
        return (string) ($owner->name ?? 'Photo');
    }
}
