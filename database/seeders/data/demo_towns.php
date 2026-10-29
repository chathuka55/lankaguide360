<?php

/*
 * Towns that get fictional demo hotels (`php artisan lg:demo-hotels`): every district capital and
 * the usual overnight bases for tourists. Places further than 20 km from all of these get their
 * own demo town automatically.
 *
 * [town, district slug, lat, lng, coastal]
 */
return [
    // District capitals
    ['Colombo', 'colombo', 6.9271, 79.8612, true],
    ['Gampaha', 'gampaha', 7.0917, 79.9997, false],
    ['Kalutara', 'kalutara', 6.5854, 79.9607, true],
    ['Kandy', 'kandy', 7.2906, 80.6337, false],
    ['Matale', 'matale', 7.4675, 80.6234, false],
    ['Nuwara Eliya', 'nuwara-eliya', 6.9497, 80.7891, false],
    ['Galle', 'galle', 6.0535, 80.2210, true],
    ['Matara', 'matara', 5.9549, 80.5550, true],
    ['Hambantota', 'hambantota', 6.1241, 81.1185, true],
    ['Jaffna', 'jaffna', 9.6615, 80.0255, true],
    ['Kilinochchi', 'kilinochchi', 9.3803, 80.3770, false],
    ['Mannar', 'mannar', 8.9810, 79.9044, true],
    ['Vavuniya', 'vavuniya', 8.7514, 80.4971, false],
    ['Mullaitivu', 'mullaitivu', 9.2671, 80.8142, true],
    ['Batticaloa', 'batticaloa', 7.7310, 81.6747, true],
    ['Ampara', 'ampara', 7.2975, 81.6820, false],
    ['Trincomalee', 'trincomalee', 8.5874, 81.2152, true],
    ['Kurunegala', 'kurunegala', 7.4863, 80.3647, false],
    ['Puttalam', 'puttalam', 8.0362, 79.8283, true],
    ['Anuradhapura', 'anuradhapura', 8.3114, 80.4037, false],
    ['Polonnaruwa', 'polonnaruwa', 7.9403, 81.0188, false],
    ['Badulla', 'badulla', 6.9934, 81.0550, false],
    ['Monaragala', 'monaragala', 6.8728, 81.3507, false],
    ['Ratnapura', 'ratnapura', 6.6828, 80.3992, false],
    ['Kegalle', 'kegalle', 7.2513, 80.3464, false],

    // Tourist bases
    ['Negombo', 'gampaha', 7.2083, 79.8358, true],
    ['Beruwala', 'kalutara', 6.4788, 79.9828, true],
    ['Bentota', 'galle', 6.4210, 79.9980, true],
    ['Hikkaduwa', 'galle', 6.1395, 80.1063, true],
    ['Unawatuna', 'galle', 6.0097, 80.2496, true],
    ['Weligama', 'matara', 5.9741, 80.4296, true],
    ['Mirissa', 'matara', 5.9483, 80.4716, true],
    ['Deniyaya', 'matara', 6.3434, 80.5593, false],
    ['Tangalle', 'hambantota', 6.0243, 80.7941, true],
    ['Tissamaharama', 'hambantota', 6.2785, 81.2876, false],
    ['Kataragama', 'monaragala', 6.4133, 81.3336, false],
    ['Wellawaya', 'monaragala', 6.7375, 81.1033, false],
    ['Udawalawe', 'ratnapura', 6.4740, 80.8880, false],
    ['Ella', 'badulla', 6.8667, 81.0466, false],
    ['Haputale', 'badulla', 6.7686, 80.9580, false],
    ['Hatton', 'nuwara-eliya', 6.8916, 80.5955, false],
    ['Kitulgala', 'kegalle', 6.9919, 80.4176, false],
    ['Sigiriya', 'matale', 7.9497, 80.7547, false],
    ['Dambulla', 'matale', 7.8742, 80.6511, false],
    ['Habarana', 'anuradhapura', 8.0390, 80.7486, false],
    ['Arugam Bay', 'ampara', 6.8406, 81.8369, true],
    ['Pasikudah', 'batticaloa', 7.9290, 81.5610, true],
    ['Nilaveli', 'trincomalee', 8.6981, 81.1907, true],
    ['Kalpitiya', 'puttalam', 8.2297, 79.7616, true],
];
