<?php

/*
 * Fleet types per tier and group size (SRS 5.2 and 5.5).
 * Rates are PLACEHOLDERS in USD for development; the admin sets real rates in phase 4.
 * Every tier covers 1–45 travellers without gaps.
 *
 * Columns: type, example model, min pax, max pax, luggage capacity (bags), day rate, km rate.
 */
return [
    'budget' => [
        ['Car', 'Toyota Axio', 1, 3, 3, 35.00, 0.30],
        ['Standard van', 'Toyota HiAce', 4, 6, 6, 45.00, 0.40],
        ['High-roof van', 'Toyota KDH high-roof', 7, 9, 9, 55.00, 0.45],
        ['Mini coach', 'Toyota Coaster', 10, 20, 20, 90.00, 0.70],
        ['Large coach', 'Ashok Leyland coach', 21, 45, 45, 140.00, 1.00],
    ],
    'premium' => [
        ['Hybrid sedan / SUV', 'Toyota Prius or Honda Vezel', 1, 3, 3, 50.00, 0.40],
        ['Flat-roof KDH van', 'Toyota KDH flat-roof', 4, 6, 6, 60.00, 0.50],
        ['High-roof KDH van', 'Toyota KDH high-roof, reclining seats', 7, 9, 9, 70.00, 0.55],
        ['Mini coach with AC', 'Toyota Coaster with AC', 10, 20, 20, 110.00, 0.80],
        ['Large coach with AC', 'Air-conditioned coach', 21, 45, 45, 170.00, 1.15],
    ],
    'luxury' => [
        ['Luxury sedan or Land Cruiser', 'Mercedes E-Class or Toyota Land Cruiser', 1, 3, 4, 110.00, 0.70],
        ['Premium van with extra legroom', 'Toyota HiAce premium, captain seats', 4, 6, 6, 120.00, 0.80],
        ['Luxury mini coach', 'Toyota Coaster luxury', 7, 9, 12, 150.00, 0.95],
        ['Luxury coach', 'Luxury touring coach', 10, 45, 45, 250.00, 1.50],
    ],
];
