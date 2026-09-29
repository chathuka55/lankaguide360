<?php

/*
 * Admin-editable settings (SRS 5.4, 7.2, NFR-16). Values are PLACEHOLDERS to be confirmed
 * by the business before launch. Percentages are stored as plain numbers (10 = 10%).
 */
return [
    'currency' => 'USD',

    // Service fee % per tier, and tax % applied to the subtotal (SRS 5.4).
    'service_fee_budget' => '8',
    'service_fee_premium' => '10',
    'service_fee_luxury' => '12',
    'tax_percent' => '0',

    // Display exchange rates from USD (NFR-16).
    'usd_lkr' => '300',
    'usd_eur' => '0.92',

    // "From $X per person per day" on builder step 1 (SRS 5.1).
    'tier_from_price_budget' => '45',
    'tier_from_price_premium' => '110',
    'tier_from_price_luxury' => '250',

    // Meals not included in the room rate, per adult (children pay half, infants free).
    'meal_breakfast' => '8',
    'meal_lunch' => '12',
    'meal_dinner' => '15',

    // Children younger than this enter sites free (SRS 5.4).
    'ticket_child_free_age' => '5',

    // Daily hours limit used by the itinerary engine (SRS 5.3 step 3).
    'day_limit_hours_budget' => '10',
    'day_limit_hours_premium' => '9',
    'day_limit_hours_luxury' => '8',
];
