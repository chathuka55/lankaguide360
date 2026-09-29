<?php

/*
 * Site-wide LankaGuide360 settings. Contact details below are placeholders:
 * set the real values in .env before going live.
 */
return [

    'tagline' => 'Build your Sri Lanka trip in minutes. A local agent confirms every detail.',

    'contact' => [
        'email' => env('CONTACT_EMAIL', 'contact@lankaguide360.lk'),
        'phone' => env('CONTACT_PHONE', '+94 11 234 5678'),
        // Digits only, with country code, for https://wa.me/<number>
        'whatsapp' => env('CONTACT_WHATSAPP', '94771234567'),
        'address' => env('CONTACT_ADDRESS', 'Colombo, Sri Lanka'),
        // Office pin on the Contact page map.
        'lat' => (float) env('CONTACT_LAT', 6.9271),
        'lng' => (float) env('CONTACT_LNG', 79.8612),
        // Receives contact-form messages and new trip requests.
        'admin_email' => env('ADMIN_EMAIL', 'admin@lankaguide360.test'),
    ],

    'social' => [
        'facebook' => env('SOCIAL_FACEBOOK', 'https://www.facebook.com/'),
        'instagram' => env('SOCIAL_INSTAGRAM', 'https://www.instagram.com/'),
        'youtube' => env('SOCIAL_YOUTUBE', 'https://www.youtube.com/'),
        'tiktok' => env('SOCIAL_TIKTOK', 'https://www.tiktok.com/'),
    ],

    // Descriptive User-Agent for every outbound HTTP call (Wikipedia, Commons, OSM).
    'user_agent' => env('HTTP_USER_AGENT', 'LankaGuide360/1.0 (contact@lankaguide360.lk)'),

    /*
     * Data importers (phase 3, `php artisan lg:import-*`). Free sources only; see docs/BUILD-PROMPTS.md §3.
     */
    'import' => [
        // Raw API responses are cached here so re-runs don't re-download (use --fresh to bypass).
        'cache_path' => storage_path('app/import-cache'),
        'geojson_path' => public_path('geo/lk-districts.geojson'),

        'timeout' => 20,
        'retries' => 3,
        'retry_sleep_ms' => 2000,

        // Minimum pause between two network requests to the same service.
        'delay_ms' => [
            'wikimedia' => 300,   // Wikipedia + Commons API, sequential
            'download' => 500,    // image files from upload.wikimedia.org
            'overpass' => 10000,  // one district query at a time, 10 s apart
        ],

        'geoboundaries_api' => 'https://www.geoboundaries.org/api/current/gbOpen/LKA/ADM2/',
        'geojson_max_bytes' => 2 * 1024 * 1024,
        'wikipedia_rest' => 'https://en.wikipedia.org/api/rest_v1',
        'wikipedia_api' => 'https://en.wikipedia.org/w/api.php',
        'commons_api' => 'https://commons.wikimedia.org/w/api.php',
        // Swap for a mirror (e.g. https://overpass.kumi.systems/api/interpreter) if the main server is busy.
        'overpass_url' => env('OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),

        'images_per_place' => 6,
        'min_image_width' => 1200,
        'suggestions_per_district' => 25,
    ],

    // Where trips can start and end (FR-07). Coordinates are used by the itinerary engine.
    'arrival_points' => [
        'BIA Katunayake' => ['label' => 'Bandaranaike International Airport (Katunayake)', 'lat' => 7.1808, 'lng' => 79.8841],
        'Colombo' => ['label' => 'Colombo city', 'lat' => 6.9271, 'lng' => 79.8612],
        'Mattala' => ['label' => 'Mattala Rajapaksa International Airport', 'lat' => 6.2845, 'lng' => 81.1240],
    ],

    /*
     * Itinerary engine (SRS 5.3) and road routing.
     */
    'routing' => [
        'provider' => env('ROUTING_PROVIDER', 'openrouteservice'),
        'ors_key' => env('ORS_API_KEY'),
        'ors_url' => 'https://api.openrouteservice.org/v2/directions/driving-car/geojson',
        // Offline estimate when no key is set or the API fails (NFR-14).
        'road_factor' => 1.35,
        'speed_kmh' => 45,
        'hill_speed_kmh' => 30,
        // Rough box around the hill country (Kandy, Nuwara Eliya, Badulla) where roads are slow.
        'hill_box' => ['south' => 6.70, 'west' => 80.45, 'north' => 7.45, 'east' => 81.20],
    ],

    'itinerary' => [
        'start_time' => ['budget' => '07:30', 'premium' => '08:00', 'luxury' => '08:00'],
        'sunrise_start' => '05:30',
        'lunch' => ['from' => '12:30', 'minutes' => 60],
        'max_stops' => ['budget' => 5, 'premium' => 4, 'luxury' => 3],
        // Fallback when the settings table has no value (admin-editable there).
        'day_limit_hours' => ['budget' => 10, 'premium' => 9, 'luxury' => 8],
        'warn_drive_minutes' => ['budget' => 360, 'premium' => 360, 'luxury' => 240],
        'hotel_search_km' => 40,
    ],

    // License choices for photos an admin uploads or imports from a URL (the owner is recorded too).
    'media_licenses' => [
        'All rights reserved, used with permission',
        'Partner photo, used with written permission',
        'CC0',
        'CC BY 4.0',
        'CC BY-SA 4.0',
        'Public domain',
    ],

    // Hotel amenity suggestions in the admin (FR-10 filters use pool and beach_front).
    'amenities' => ['wifi', 'pool', 'beach_front', 'air_conditioning', 'restaurant', 'bar', 'spa', 'gym', 'parking', 'kids_club', 'room_service', 'wheelchair'],

    // Interest categories (SRS 1.3 / 4.1). Phase 2 moves these into the categories table.
    'categories' => [
        'beach-coastal' => 'Beach & Coastal',
        'historical-cultural' => 'Historical & Cultural',
        'hill-country' => 'Hill Country',
        'nature-wildlife' => 'Nature & Wildlife',
        'hiking-adventure' => 'Hiking & Adventure',
        'hidden-gems' => 'Hidden Gems',
    ],

];
