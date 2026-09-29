<?php

/*
 * Sri Lanka's 9 provinces and 25 administrative districts.
 * lat/lng are approximate district centroids; the geoBoundaries importer (phase 3)
 * recalculates them from the official ADM2 shapes.
 */
return [
    'Western' => [
        ['name' => 'Colombo', 'lat' => 6.8700, 'lng' => 80.0200],
        ['name' => 'Gampaha', 'lat' => 7.0700, 'lng' => 80.0800],
        ['name' => 'Kalutara', 'lat' => 6.6000, 'lng' => 80.1500],
    ],
    'Central' => [
        ['name' => 'Kandy', 'lat' => 7.3000, 'lng' => 80.7000],
        ['name' => 'Matale', 'lat' => 7.6700, 'lng' => 80.7000],
        ['name' => 'Nuwara Eliya', 'lat' => 6.9800, 'lng' => 80.7000],
    ],
    'Southern' => [
        ['name' => 'Galle', 'lat' => 6.1800, 'lng' => 80.2800],
        ['name' => 'Matara', 'lat' => 6.1000, 'lng' => 80.5300],
        ['name' => 'Hambantota', 'lat' => 6.2500, 'lng' => 81.1000],
    ],
    'Northern' => [
        ['name' => 'Jaffna', 'lat' => 9.6800, 'lng' => 80.1000],
        ['name' => 'Kilinochchi', 'lat' => 9.4000, 'lng' => 80.4000],
        ['name' => 'Mannar', 'lat' => 8.9000, 'lng' => 80.0500],
        ['name' => 'Vavuniya', 'lat' => 8.8000, 'lng' => 80.5000],
        ['name' => 'Mullaitivu', 'lat' => 9.1500, 'lng' => 80.6500],
    ],
    'Eastern' => [
        ['name' => 'Trincomalee', 'lat' => 8.5500, 'lng' => 81.1000],
        ['name' => 'Batticaloa', 'lat' => 7.7000, 'lng' => 81.5800],
        ['name' => 'Ampara', 'lat' => 7.1500, 'lng' => 81.6000],
    ],
    'North Western' => [
        ['name' => 'Kurunegala', 'lat' => 7.5500, 'lng' => 80.3000],
        ['name' => 'Puttalam', 'lat' => 8.0500, 'lng' => 79.9500],
    ],
    'North Central' => [
        ['name' => 'Anuradhapura', 'lat' => 8.3500, 'lng' => 80.4500],
        ['name' => 'Polonnaruwa', 'lat' => 7.9500, 'lng' => 81.0500],
    ],
    'Uva' => [
        ['name' => 'Badulla', 'lat' => 7.0000, 'lng' => 81.1000],
        ['name' => 'Monaragala', 'lat' => 6.7500, 'lng' => 81.3000],
    ],
    'Sabaragamuwa' => [
        ['name' => 'Ratnapura', 'lat' => 6.6000, 'lng' => 80.5500],
        ['name' => 'Kegalle', 'lat' => 7.2000, 'lng' => 80.3500],
    ],
];
