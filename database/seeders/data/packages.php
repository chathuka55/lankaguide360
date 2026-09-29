<?php

/*
 * Ready-made packages (SRS 3.3, phase 12). Each package keeps a template trip (status draft,
 * no traveller) whose days list the places below by name (database/seeders/data/places.php).
 * Places without coordinates or not yet published are skipped when a traveller customizes the
 * package. Prices are PLACEHOLDERS per person until the admin sets them.
 *
 * days: [title, [place names], overnight town]
 */
return [
    [
        'name' => 'Cultural Triangle Classic',
        'slug' => 'cultural-triangle-classic',
        'tier' => 'premium',
        'from_price' => 690,
        'featured' => true,
        'summary' => 'Sigiriya, Dambulla, Polonnaruwa and Kandy: the ancient cities and the Temple of the Tooth in five relaxed days.',
        'inclusions' => "Air-conditioned car with chauffeur-guide\n4 nights in premium hotels, breakfast and dinner\nAirport pick-up and drop-off",
        'exclusions' => "Entrance tickets\nLunches and drinks\nTips",
        'days' => [
            ['Airport to Sigiriya via Dambulla', ['Dambulla Cave Temple'], 'Sigiriya'],
            ['Sigiriya Rock at sunrise and Pidurangala', ['Sigiriya Rock Fortress', 'Pidurangala Rock'], 'Sigiriya'],
            ['Ancient city of Polonnaruwa', ['Ancient City of Polonnaruwa', 'Gal Vihara', 'Minneriya National Park'], 'Sigiriya'],
            ['To Kandy via Matale', ['Aluvihare Rock Temple', 'Temple of the Sacred Tooth Relic', 'Kandy Lake'], 'Kandy'],
            ['Peradeniya Gardens and departure', ['Royal Botanical Gardens, Peradeniya'], null],
        ],
    ],
    [
        'name' => 'Hill Country and Tea Trails',
        'slug' => 'hill-country-tea-trails',
        'tier' => 'premium',
        'from_price' => 620,
        'featured' => true,
        'summary' => 'Kandy, tea estates around Nuwara Eliya, Horton Plains and the Nine Arch Bridge in Ella.',
        'inclusions' => "Car with chauffeur-guide\nHotels with breakfast\nTea factory visit",
        'exclusions' => "Train tickets\nEntrance tickets\nLunches and dinners",
        'days' => [
            ['Kandy and the Temple of the Tooth', ['Temple of the Sacred Tooth Relic', 'Kandy Lake'], 'Kandy'],
            ['Tea country to Nuwara Eliya', ['Ceylon Tea Museum', 'Ramboda Falls', 'Gregory Lake'], 'Nuwara Eliya'],
            ['Horton Plains and World\'s End', ['Horton Plains National Park', 'Hakgala Botanical Garden'], 'Nuwara Eliya'],
            ['To Ella', ['Nine Arch Bridge', 'Ravana Falls'], 'Ella'],
            ['Little Adam\'s Peak or Ella Rock, then departure', ['Ella Rock'], null],
        ],
    ],
    [
        'name' => 'Southern Beaches and Galle Fort',
        'slug' => 'southern-beaches-galle-fort',
        'tier' => 'budget',
        'from_price' => 380,
        'featured' => true,
        'summary' => 'Easy beach days on the south coast: Bentota, Galle Fort, Unawatuna and whale watching from Mirissa.',
        'inclusions' => "Car with driver\nGuesthouses with breakfast",
        'exclusions' => "Whale-watching boat\nMeals other than breakfast\nTips",
        'days' => [
            ['Airport to Bentota', ['Bentota Beach'], 'Bentota'],
            ['Galle Fort at sunset', ['Brief Garden', 'Galle Fort'], 'Galle'],
            ['Unawatuna and Koggala', ['Unawatuna Beach', 'Koggala'], 'Mirissa'],
            ['Mirissa and Weligama', ['Mirissa Beach', 'Weligama Bay'], 'Mirissa'],
            ['Beach morning and departure', [], null],
        ],
    ],
    [
        'name' => 'Wildlife Safari Circuit',
        'slug' => 'wildlife-safari-circuit',
        'tier' => 'premium',
        'from_price' => 840,
        'featured' => false,
        'summary' => 'Elephants at Udawalawe, leopards at Yala and birds at Bundala, with a night by the sea at Tangalle.',
        'inclusions' => "Car with chauffeur-guide\nJeep safaris at Udawalawe and Yala\nHotels with breakfast and dinner",
        'exclusions' => "Park entrance tickets\nLunches\nTips for trackers",
        'days' => [
            ['To Udawalawe', ['Udawalawe National Park'], 'Udawalawe'],
            ['Morning safari at Yala', ['Yala National Park'], 'Tissamaharama'],
            ['Birds of Bundala', ['Bundala National Park'], 'Tangalle'],
            ['Tangalle beach and departure', ['Tangalle Beach'], null],
        ],
    ],
    [
        'name' => 'Grand Sri Lanka in 10 Days',
        'slug' => 'grand-sri-lanka-10-days',
        'tier' => 'luxury',
        'from_price' => 2150,
        'featured' => true,
        'summary' => 'The full loop: ancient cities, Kandy, tea country, Ella, a Yala safari and the south coast, in luxury hotels.',
        'inclusions' => "Luxury car with national guide\n9 nights in luxury hotels, half board\nOne jeep safari",
        'exclusions' => "Entrance tickets\nLunches and drinks\nTips",
        'days' => [
            ['Airport to Sigiriya', ['Dambulla Cave Temple'], 'Sigiriya'],
            ['Sigiriya Rock Fortress', ['Sigiriya Rock Fortress', 'Pidurangala Rock'], 'Sigiriya'],
            ['Polonnaruwa', ['Ancient City of Polonnaruwa', 'Gal Vihara'], 'Sigiriya'],
            ['To Kandy', ['Aluvihare Rock Temple', 'Temple of the Sacred Tooth Relic'], 'Kandy'],
            ['Peradeniya and tea country', ['Royal Botanical Gardens, Peradeniya', 'Ramboda Falls'], 'Nuwara Eliya'],
            ['Horton Plains', ['Horton Plains National Park', 'Gregory Lake'], 'Nuwara Eliya'],
            ['Ella', ['Nine Arch Bridge', 'Ella Rock'], 'Ella'],
            ['Yala safari', ['Yala National Park'], 'Tissamaharama'],
            ['South coast to Galle', ['Mirissa Beach', 'Galle Fort'], 'Galle'],
            ['Departure', [], null],
        ],
    ],
    [
        'name' => 'East Coast Escape',
        'slug' => 'east-coast-escape',
        'tier' => 'budget',
        'from_price' => 420,
        'featured' => false,
        'summary' => 'Quiet beaches and coral at Trincomalee and Pasikudah, with Koneswaram Temple and Pigeon Island.',
        'inclusions' => "Car with driver\nBeach hotels with breakfast",
        'exclusions' => "Snorkelling boat\nMeals other than breakfast",
        'days' => [
            ['Airport to Trincomalee', ['Koneswaram Temple'], 'Trincomalee'],
            ['Pigeon Island and Nilaveli', ['Pigeon Island National Park', 'Nilaveli Beach'], 'Trincomalee'],
            ['To Pasikudah', ['Pasikudah Beach'], 'Pasikudah'],
            ['Kalkudah and departure', ['Kalkudah Beach'], null],
        ],
    ],
];
