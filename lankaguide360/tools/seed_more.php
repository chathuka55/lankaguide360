<?php
/**
 * seed_more.php — Adds more destinations (with exact coordinates), hotels and
 * sample vehicles for guides/drivers, and backfills coordinates on existing
 * hotels. Idempotent: rows are keyed by slug / name / registration so it is
 * safe to run multiple times.
 *
 *   php tools/seed_more.php
 */

require_once __DIR__ . '/../config/db.php';
$db = getDbConnection();
$nl = (php_sapi_name() === 'cli') ? "\n" : "<br>\n";
function say(string $s) { global $nl; echo $s . $nl; }

// Lookup maps -------------------------------------------------------------
$catId = [];
foreach ($db->query("SELECT id, slug FROM categories") as $c) $catId[$c['slug']] = (int)$c['id'];
$locId = [];
foreach ($db->query("SELECT id, slug FROM locations") as $l) $locId[$l['slug']] = (int)$l['id'];

// =========================================================================
// 1) More destinations (exact lat/lng), with categories + a few activities
// =========================================================================
$destinations = [
    ['Dambulla Cave Temple','dambulla-cave','Cultural Triangle','Matale','dambulla',
     'A golden rock-cave complex of 5 shrines with 150+ Buddha statues and painted ceilings.',
     'The Dambulla Royal Cave Temple is a UNESCO World Heritage site dating back to the 1st century BC, famous for its vividly painted cave ceilings and reclining Buddha statues.',
     'Year-round',1.5,2000,'low','popular',7.856700,80.649200,4.6,
     ['culture'=>5,'family'=>2], [['Cave murals tour','Guided walk through all five painted caves.',1.5,2000]]],

    ['Polonnaruwa Ancient City','polonnaruwa-ancient','Cultural Triangle','Polonnaruwa','polonnaruwa',
     'The remarkably preserved ruins of a medieval royal capital, best explored by bicycle.',
     'Sri Lanka\'s second ancient capital, Polonnaruwa is a UNESCO site of palaces, stupas and the serene rock-carved Buddhas of Gal Vihara.',
     'May–Sep',3.5,4500,'medium','popular',7.940300,81.018800,4.6,
     ['culture'=>5,'adventure'=>2], [['Cycle the ruins','Bike hire and a loop of the ancient city.',3.0,1500]]],

    ['Anuradhapura Sacred City','anuradhapura-sacred','Cultural Triangle','Anuradhapura','anuradhapura',
     'Sri Lanka\'s first capital — vast dagobas, monasteries and the sacred Sri Maha Bodhi tree.',
     'A living pilgrimage city and UNESCO site spanning 1,300 years of history, with colossal brick stupas and the world\'s oldest documented tree.',
     'May–Sep',3.5,4500,'medium','hidden_gem',8.311400,80.403700,4.5,
     ['culture'=>5], []],

    ['Nilaveli Beach','nilaveli-beach','East Coast','Trincomalee','trincomalee',
     'Powder-white sand and calm turquoise water near Pigeon Island snorkelling.',
     'Nilaveli is one of Sri Lanka\'s finest east-coast beaches, a launch point for Pigeon Island National Park snorkelling and diving.',
     'May–Sep',3.0,0,'low','hidden_gem',8.702900,81.190900,4.5,
     ['beaches'=>5,'family'=>3], [['Pigeon Island snorkelling','Boat + reef snorkelling with turtles and reef sharks.',3.0,4000]]],

    ['Jaffna Fort & Nallur','jaffna-heritage','Northern','Jaffna','jaffna',
     'The Dutch fort, Nallur Kandaswamy temple and a distinct northern Tamil culture and cuisine.',
     'Jaffna rewards curious travellers with a star-shaped Dutch fort, the colourful Nallur Kovil, island day-trips and famous Jaffna crab curry.',
     'Year-round',3.0,0,'low','hidden_gem',9.661500,80.025500,4.3,
     ['culture'=>4,'food'=>3], []],

    ['Horton Plains & World\'s End','horton-plains','Hill Country','Nuwara Eliya','nuwara-eliya-town',
     'A misty high-altitude plateau with a cliff that drops 870m — the famous World\'s End.',
     'Horton Plains National Park is a cloud-forest plateau at 2,100m, home to a scenic loop trail past Baker\'s Falls to the sheer World\'s End escarpment.',
     'Dec–Mar',4.0,8000,'medium','popular',6.802100,80.806500,4.7,
     ['hill-country'=>5,'adventure'=>3], [['World\'s End loop hike','Early-morning 9km loop before the mist rolls in.',4.0,8000]]],

    ['Hikkaduwa Beach','hikkaduwa-beach','Southern Coast','Galle','hikkaduwa',
     'A lively surf-and-snorkel town with a shallow coral sanctuary and turtle sightings.',
     'Hikkaduwa pairs a buzzing beach strip with a protected coral reef you can snorkel straight off the sand, plus reliable beginner surf.',
     'Nov–Apr',3.0,0,'low','popular',6.139500,80.106300,4.4,
     ['beaches'=>5,'adventure'=>2], [['Coral reef snorkelling','Glass-bottom boat and reef snorkelling.',2.0,3000]]],

    ['Unawatuna Beach','unawatuna-beach','Southern Coast','Galle','galle',
     'A sheltered golden crescent bay near Galle, great for swimming and sunset dinners.',
     'Unawatuna is a calm, palm-fringed bay just south of Galle Fort, popular for safe swimming, snorkelling at the reef and beach-side dining.',
     'Nov–Apr',3.0,0,'low','popular',6.009700,80.248900,4.5,
     ['beaches'=>5,'wellness'=>2], []],

    ['Royal Botanical Gardens, Peradeniya','peradeniya-gardens','Hill Country','Kandy','kandy',
     '147 acres of curated tropical gardens beside the Mahaweli River near Kandy.',
     'The Peradeniya gardens hold over 4,000 plant species, an avenue of towering palms, an orchid house and a giant Java fig — a relaxed half-day near Kandy.',
     'Year-round',2.5,3000,'low','popular',7.271700,80.596000,4.5,
     ['hill-country'=>3,'family'=>4], []],

    ['Minneriya National Park','minneriya','Cultural Triangle','Polonnaruwa','dambulla',
     'Home to "The Gathering" — hundreds of wild elephants around the ancient reservoir.',
     'Minneriya is famed for the seasonal Gathering, when large herds of elephants converge on the receding tank — one of Asia\'s great wildlife spectacles.',
     'Jul–Sep',3.5,5500,'medium','popular',8.033300,80.900000,4.6,
     ['wildlife'=>5,'family'=>3], [['Elephant Gathering safari','Afternoon jeep safari to the reservoir plains.',4.0,8000]]],

    ['Kitulgala Adventure','kitulgala','Hill Country','Kegalle','kegalle',
     'Sri Lanka\'s white-water rafting capital, set in rainforest along the Kelani River.',
     'Kitulgala offers grade 2–3 white-water rafting, canyoning and jungle treks in the lush western rainforest — the filming site of The Bridge on the River Kwai.',
     'May–Dec',4.0,0,'low','hidden_gem',6.989700,80.414200,4.5,
     ['adventure'=>5,'hill-country'=>2], [['White-water rafting','Guided grade 2–3 rafting run with gear.',3.0,6500]]],

    ['Pidurangala Rock','pidurangala','Cultural Triangle','Matale','sigiriya-town',
     'A rugged climb facing Sigiriya, with the island\'s best sunrise view of the rock.',
     'Pidurangala is the boulder-strewn peak beside Sigiriya, a short scramble to a summit that frames the Lion Rock at sunrise — a favourite of hikers.',
     'Jan–Mar',2.0,1000,'low','hidden_gem',7.957500,80.760300,4.7,
     ['adventure'=>4,'culture'=>3], [['Sunrise summit climb','Pre-dawn scramble for the Sigiriya sunrise panorama.',2.0,1000]]],
];

$destStmt = $db->prepare(
    "INSERT INTO destinations
     (name, slug, region, district, location_id, short_description, full_description, best_season,
      avg_visit_hours, entry_fee_lkr, budget_tier, popularity_tier, latitude, longitude, rating)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
$dcStmt  = $db->prepare("INSERT INTO destination_categories (destination_id, category_id, weight) VALUES (?,?,?)");
$actStmt = $db->prepare("INSERT INTO activities (destination_id, title, description, duration_hours, price_lkr) VALUES (?,?,?,?,?)");

$addedDest = 0;
foreach ($destinations as $d) {
    $exists = $db->prepare("SELECT id FROM destinations WHERE slug = ?");
    $exists->execute([$d[1]]);
    if ($exists->fetchColumn()) { say("· destination exists: {$d[0]}"); continue; }

    $destStmt->execute([
        $d[0], $d[1], $d[2], $d[3], $locId[$d[4]] ?? null, $d[5], $d[6], $d[7],
        $d[8], $d[9], $d[10], $d[11], $d[12], $d[13], $d[14],
    ]);
    $id = (int)$db->lastInsertId();
    foreach ($d[15] as $slug => $w) {
        if (isset($catId[$slug])) $dcStmt->execute([$id, $catId[$slug], $w]);
    }
    foreach ($d[16] as $a) {
        $actStmt->execute([$id, $a[0], $a[1], $a[2], $a[3]]);
    }
    say("✓ destination added: {$d[0]}");
    $addedDest++;
}

// =========================================================================
// 2) Backfill coordinates on the original hotels (they were seeded NULL)
// =========================================================================
$hotelCoords = [
    'Cinnamon Grand Colombo' => [6.916500, 79.848000],
    'Heritance Kandalama'    => [7.857000, 80.687000],
    '98 Acres Resort'        => [6.869000, 81.049000],
    'Jetwing Lighthouse'     => [6.041000, 80.205000],
    'Araliya Green Hills'    => [6.960000, 80.766000],
    'Mango House Mirissa'    => [5.946000, 80.459000],
    'Amaya Lake'             => [7.920000, 80.630000],
    'Hideaway Arugam Bay'    => [6.840000, 81.836000],
    'Jetwing Jaffna'         => [9.662000, 80.009000],
    'Colombo City Hostel'    => [6.927000, 79.865000],
];
$coordStmt = $db->prepare("UPDATE hotels SET latitude=?, longitude=? WHERE name=? AND (latitude IS NULL OR longitude IS NULL)");
foreach ($hotelCoords as $name => $ll) { $coordStmt->execute([$ll[0], $ll[1], $name]); }
say("✓ backfilled coordinates on existing hotels");

// =========================================================================
// 3) More hotels (with coordinates) + rooms
// =========================================================================
$hotels = [
    ['Uga Chena Huts Yala','Southern Coast','Hambantota','Yala, Palatupana',5,'high',55000,'Pool,Spa,WiFi,Safari',4.7,6.276000,81.409000,
     [['Cabin Suite',2,55000,6]]],
    ['Cinnamon Lodge Habarana','Cultural Triangle','Habarana','Habarana',4,'medium',24000,'Pool,WiFi,Nature,Restaurant',4.5,8.037000,80.750000,
     [['Deluxe Room',2,24000,10],['Family Room',4,38000,4]]],
    ['Earl\'s Regency Kandy','Hill Country','Kandy','Tennekumbura, Kandy',5,'high',30000,'Pool,Spa,WiFi,Gym',4.6,7.279000,80.669000,
     [['Deluxe Room',2,30000,8]]],
    ['The Fortress Resort Koggala','Southern Coast','Galle','Koggala',5,'high',42000,'Pool,Spa,WiFi,Beachfront',4.7,5.984000,80.329000,
     [['Ocean Suite',2,42000,6]]],
    ['Trinco Blu by Cinnamon','East Coast','Trincomalee','Uppuveli',4,'medium',21000,'Pool,WiFi,Beachfront',4.4,8.590000,81.227000,
     [['Sea View Room',2,21000,8]]],
    ['Grand Hotel Nuwara Eliya','Hill Country','Nuwara Eliya','Grand Hotel Rd',5,'high',34000,'WiFi,Golf,Restaurant,Bar',4.5,6.969000,80.772000,
     [['Colonial Deluxe',2,34000,6]]],
    ['Thilanka Hotel Kandy','Hill Country','Kandy','Sangaraja Mawatha',4,'medium',18000,'Pool,WiFi,City View',4.3,7.296000,80.642000,
     [['Standard Double',2,18000,10]]],
    ['Heritance Ahungalla','Southern Coast','Galle','Ahungalla',5,'high',36000,'Pool,Spa,WiFi,Beachfront',4.6,6.311000,80.040000,
     [['Deluxe Room',2,36000,8]]],
];
$hotelStmt = $db->prepare(
    "INSERT INTO hotels (name, region, district, address, star_rating, budget_tier, price_per_night_lkr, amenities, rating, latitude, longitude)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)"
);
$roomStmt = $db->prepare("INSERT INTO rooms (hotel_id, room_type, capacity, price_per_night_lkr, qty_available) VALUES (?,?,?,?,?)");
$addedHotels = 0;
foreach ($hotels as $h) {
    $exists = $db->prepare("SELECT id FROM hotels WHERE name = ?");
    $exists->execute([$h[0]]);
    if ($exists->fetchColumn()) { say("· hotel exists: {$h[0]}"); continue; }
    $hotelStmt->execute([$h[0],$h[1],$h[2],$h[3],$h[4],$h[5],$h[6],$h[7],$h[8],$h[9],$h[10]]);
    $hid = (int)$db->lastInsertId();
    foreach ($h[11] as $rm) { $roomStmt->execute([$hid, $rm[0], $rm[1], $rm[2], $rm[3]]); }
    say("✓ hotel added: {$h[0]}");
    $addedHotels++;
}

// =========================================================================
// 4) Sample vehicles for guides/drivers who don't own one yet
//    ['owner_kind','owner_id']  → links the vehicle back to that guide/driver
// =========================================================================
$vehicles = [
    ['Toyota Aqua','car','SP-AQ-1001',4,'Southern Coast',1,1,'guide',2,7500,'guide',2],
    ['Suzuki Every','van','CP-EV-1002',7,'Cultural Triangle',1,1,'guide',3,9000,'guide',3],
    ['Mahindra Bolero','suv','EP-BO-1003',6,'East Coast',1,1,'guide',5,10000,'guide',5],
    ['Toyota Axio','car','WP-AX-1004',4,'Western',1,1,'guide',6,8000,'guide',6],
    ['Nissan Urvan','van','SP-UV-1005',12,'Southern Coast',1,1,'driver',3,11000,'driver',3],
    ['Toyota Hiace GL','van','CP-HG-1006',14,'Cultural Triangle',1,1,'driver',5,11500,'driver',5],
];
$vehStmt = $db->prepare(
    "INSERT INTO vehicles (name, type, registration_no, seats, region, has_driver, is_rentable, owner_type, owner_ref_id, rate_per_day_lkr)
     VALUES (?,?,?,?,?,?,?,?,?,?)"
);
$addedVeh = 0;
foreach ($vehicles as $v) {
    $exists = $db->prepare("SELECT id FROM vehicles WHERE registration_no = ?");
    $exists->execute([$v[2]]);
    if ($exists->fetchColumn()) { say("· vehicle exists: {$v[0]} ({$v[2]})"); continue; }
    $vehStmt->execute([$v[0],$v[1],$v[2],$v[3],$v[4],$v[5],$v[6],$v[7],$v[8],$v[9]]);
    $vid = (int)$db->lastInsertId();
    if ($v[10] === 'guide') {
        $db->prepare("UPDATE guides SET has_own_vehicle=1, vehicle_id=? WHERE id=?")->execute([$vid, $v[11]]);
    } else {
        $db->prepare("UPDATE drivers SET has_own_vehicle=1, vehicle_id=? WHERE id=?")->execute([$vid, $v[11]]);
    }
    say("✓ vehicle added: {$v[0]} → {$v[10]} #{$v[11]}");
    $addedVeh++;
}

say("Done. Destinations +{$addedDest}, hotels +{$addedHotels}, vehicles +{$addedVeh}.");
