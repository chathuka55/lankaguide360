<?php

/*
 * Self-drive hire (Sri Lankan residents with a valid Sri Lankan driving licence only).
 * PLACEHOLDER rates in USD until the admin sets real ones (Admin → Vehicles).
 * Coaches always come with a driver, so only cars, SUVs and vans are listed.
 *
 * Columns: vehicle type => [day rate, refundable deposit, km included per day].
 */
return [
    'Car' => [30.00, 100.00, 100],
    'Standard van' => [40.00, 150.00, 100],
    'Hybrid sedan / SUV' => [45.00, 150.00, 100],
    'Flat-roof KDH van' => [50.00, 200.00, 100],
    'Luxury sedan or Land Cruiser' => [95.00, 400.00, 150],
];
