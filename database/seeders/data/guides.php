<?php

/*
 * Guide options per type (SRS 5.2, 5.5, FR-13). Names are service descriptions, not
 * real people: the admin replaces them with the agency's guides in phase 4.
 * Day rates are PLACEHOLDERS in USD.
 *
 * Columns: name, type, languages, day rate.
 */
return [
    ['Chauffeur-guide (English)', 'chauffeur', ['English'], 15.00],
    ['Chauffeur-guide (English, German)', 'chauffeur', ['English', 'German'], 20.00],
    ['Chauffeur-guide (English, French)', 'chauffeur', ['English', 'French'], 20.00],
    ['National guide (English)', 'national', ['English'], 50.00],
    ['National guide (German)', 'national', ['German', 'English'], 60.00],
    ['National guide (French)', 'national', ['French', 'English'], 60.00],
    ['National guide (Chinese)', 'national', ['Chinese', 'English'], 65.00],
    ['National guide (Russian)', 'national', ['Russian', 'English'], 65.00],
    ['Site guide (English)', 'site', ['English'], 20.00],
    ['Chauffeur-guide (Sinhala, English)', 'chauffeur', ['Sinhala', 'English'], 12.00],
    ['Chauffeur-guide (Tamil, English)', 'chauffeur', ['Tamil', 'English'], 12.00],
    ['National guide (Sinhala)', 'national', ['Sinhala', 'English'], 40.00],
    ['National guide (Tamil)', 'national', ['Tamil', 'English'], 40.00],
];
