<?php

/*
 * Checked coordinates for places whose Wikipedia article has no location, a wrong one, or a
 * point that is not where visitors go (national parks: the safari entrance, not the middle of
 * the park). Entered by hand from general knowledge and cross-checked on a map — verify each
 * one with the "Check this location in Google Maps" link in Admin → Places and correct it there.
 *
 * slug => [lat, lng, why]
 */
return [
    // No coordinates in Wikipedia
    'hikkaduwa-beach' => [6.1395, 80.1063, 'Hikkaduwa beach, town centre'],
    'uppuveli-beach' => [8.6052, 81.2230, 'Uppuveli beach, north of Trincomalee'],
    'pasikudah-beach' => [7.9310, 81.5640, 'Pasikudah bay'],
    'deegavapi' => [7.2833, 81.7500, 'Deegavapi stupa (approximate)'],
    'anawilundawa-wetland-sanctuary' => [7.7000, 79.8167, 'Anawilundawa tanks'],
    'jetavanaramaya' => [8.3516, 80.4037, 'Jetavanaramaya stupa'],
    'ritigala' => [8.1167, 80.6500, 'Ritigala forest monastery entrance (approximate)'],
    'pidurangala-rock' => [7.9667, 80.7600, 'Pidurangala temple and trail start'],
    'riverston' => [7.5278, 80.7369, 'Riverston gap (approximate)'],
    'arankele-forest-monastery' => [7.6833, 80.3667, 'Arankele monastery (approximate)'],
    'seetha-amman-temple' => [6.9233, 80.8164, 'Seetha Eliya temple'],
    'ella-rock' => [6.8575, 81.0390, 'Ella Rock summit'],
    'little-adams-peak' => [6.8690, 81.0619, "Little Adam's Peak summit"],
    'maligawila-buddha-statue' => [6.7000, 81.2333, 'Maligawila statue (approximate)'],
    'belihuloya' => [6.7167, 80.7667, 'Belihuloya village'],
    'thiruketheeswaram-temple' => [9.0169, 79.9256, 'Thiruketheeswaram kovil, Mannar (approximate)'],

    // Wrong or unhelpful coordinates
    'kalkudah-beach' => [7.9170, 81.5590, 'Wikipedia point was on the west coast; Kalkudah is next to Pasikudah'],
    'kandy-lake' => [7.2925, 80.6416, 'Lake shore by the Temple of the Tooth (was ~1.5 km off)'],
    'liptons-seat' => [6.7839, 80.9634, "Lipton's Seat viewpoint (had the same point as Haputale)"],
    'yala-national-park' => [6.2756, 81.4100, 'Palatupana entrance, where safaris start (was the middle of the park)'],
    'gal-oya-national-park' => [7.2167, 81.5333, 'Inginiyagala, the boat-safari entrance (was the middle of the park)'],
    'kalpitiya' => [8.2297, 79.7616, 'Kalpitiya town'],
];
