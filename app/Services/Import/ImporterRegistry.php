<?php

namespace App\Services\Import;

use InvalidArgumentException;

/**
 * Name → importer class, used by the artisan commands, the queued job and the admin import
 * tools page. ORDER is the sequence lg:import-all runs: later steps need earlier data.
 */
final class ImporterRegistry
{
    public const IMPORTERS = [
        'districts' => GeoBoundariesImporter::class,
        'places' => WikipediaImporter::class,
        'images' => CommonsImageImporter::class,
        'hotels' => OverpassHotelImporter::class,
        'suggest-places' => OverpassPlaceSuggester::class,
    ];

    public const ORDER = ['districts', 'places', 'images', 'hotels', 'suggest-places'];

    /**
     * Admin page details: label, what it does, artisan command and the import_logs key.
     */
    public const DETAILS = [
        'districts' => ['label' => 'District boundaries', 'source' => 'geoBoundaries', 'command' => 'lg:import-districts', 'log' => 'geoboundaries',
            'description' => 'Downloads the 25 district shapes for the maps and updates district centre points.'],
        'places' => ['label' => 'Place descriptions', 'source' => 'Wikipedia', 'command' => 'lg:import-places', 'log' => 'wikipedia',
            'description' => 'Fills descriptions, coordinates and article links for places with a Wikipedia title.'],
        'images' => ['label' => 'Place photos', 'source' => 'Wikimedia Commons', 'command' => 'lg:import-images', 'log' => 'commons',
            'description' => 'Adds up to 6 freely licensed photos per place, as WebP drafts with credits.'],
        'hotels' => ['label' => 'Hotels & guest houses', 'source' => 'OpenStreetMap', 'command' => 'lg:import-hotels', 'log' => 'osm-hotels',
            'description' => 'Imports stays per district as drafts. Admins add rates and photos. Needs district boundaries.'],
        'suggest-places' => ['label' => 'Place suggestions', 'source' => 'OpenStreetMap', 'command' => 'lg:suggest-places', 'log' => 'osm-places',
            'description' => 'Suggests waterfalls, viewpoints, peaks, beaches and historic sites as draft places. Needs district boundaries.'],
    ];

    public static function make(string $name): Importer
    {
        $class = self::IMPORTERS[$name] ?? throw new InvalidArgumentException("Unknown importer [{$name}].");

        return app($class);
    }
}
