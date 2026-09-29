<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Package;
use App\Models\Place;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * sitemap.xml and robots.txt (phase 12). The sitemap lists every public page and is cached
 * for an hour; it is built by hand so it needs no extra package.
 */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('seo.sitemap', 3600, function () {
            $urls = collect([
                [route('home'), null, '1.0'],
                [route('destinations.index'), null, '0.9'],
                [route('packages.index'), null, '0.8'],
                [route('plan'), null, '0.8'],
                [route('about'), null, '0.5'],
                [route('contact'), null, '0.5'],
                [route('credits'), null, '0.2'],
                [route('privacy'), null, '0.2'],
                [route('terms'), null, '0.2'],
            ]);

            District::orderBy('name')->get(['id', 'slug'])->each(fn ($d) => $urls->push([route('districts.show', $d), null, '0.6']));

            Place::published()->with('district:id,slug')->orderBy('id')->get(['id', 'slug', 'district_id', 'updated_at'])
                ->each(fn ($p) => $urls->push([$p->url(), $p->updated_at?->toAtomString(), '0.7']));

            Package::orderBy('sort_order')->get(['id', 'slug'])->each(fn ($p) => $urls->push([route('packages.show', $p), null, '0.7']));

            $body = $urls->map(function ($u) {
                [$loc, $lastmod, $priority] = $u;

                return '<url><loc>'.e($loc).'</loc>'.($lastmod ? "<lastmod>{$lastmod}</lastmod>" : '')."<priority>{$priority}</priority></url>";
            })->join("\n");

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n{$body}\n</urlset>\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /trips/', 'Disallow: /my-trips', 'Disallow: /profile', 'Disallow: /api/', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
