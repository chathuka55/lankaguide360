<?php

/*
 * Interest categories (SRS 1.3) and the districts each one shows in the builder (SRS 4.1).
 *
 * `extra_districts` are links added beyond the SRS 4.1 table so that well-known seeded
 * places are reachable in the builder:
 *   - Colombo → Historical & Cultural (Gangaramaya, National Museum; otherwise Colombo has no places)
 *   - Trincomalee → Historical & Cultural (Koneswaram Temple)
 *   - Kegalle → Nature & Wildlife (Pinnawala Elephant Orphanage)
 * Admins can change every link later.
 */
return [
    [
        'name' => 'Beach & Coastal',
        'slug' => 'beach-coastal',
        'icon' => 'beach',
        'districts' => ['Galle', 'Matara', 'Hambantota', 'Kalutara', 'Gampaha', 'Trincomalee', 'Batticaloa', 'Ampara', 'Puttalam'],
        'extra_districts' => [],
    ],
    [
        'name' => 'Historical & Cultural',
        'slug' => 'historical-cultural',
        'icon' => 'temple',
        'districts' => ['Anuradhapura', 'Polonnaruwa', 'Matale', 'Kandy', 'Galle', 'Jaffna', 'Kurunegala'],
        'extra_districts' => ['Colombo', 'Trincomalee'],
    ],
    [
        'name' => 'Hill Country',
        'slug' => 'hill-country',
        'icon' => 'tea',
        'districts' => ['Kandy', 'Nuwara Eliya', 'Badulla', 'Kegalle'],
        'extra_districts' => [],
    ],
    [
        'name' => 'Nature & Wildlife',
        'slug' => 'nature-wildlife',
        'icon' => 'elephant',
        'districts' => ['Hambantota', 'Monaragala', 'Polonnaruwa', 'Ratnapura', 'Puttalam', 'Trincomalee'],
        'extra_districts' => ['Kegalle'],
    ],
    [
        'name' => 'Hiking & Adventure',
        'slug' => 'hiking-adventure',
        'icon' => 'mountain',
        'districts' => ['Badulla', 'Nuwara Eliya', 'Ratnapura', 'Kegalle', 'Matale', 'Kandy'],
        'extra_districts' => [],
    ],
    [
        'name' => 'Hidden Gems',
        'slug' => 'hidden-gems',
        'icon' => 'gem',
        'districts' => ['Matale', 'Anuradhapura', 'Ampara', 'Mannar', 'Monaragala', 'Kurunegala', 'Badulla'],
        'extra_districts' => [],
    ],
];
