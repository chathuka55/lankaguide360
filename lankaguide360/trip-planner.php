<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/TripRecommender.php';
$pageTitle = 'Trip Planner — LankaGuide 360';
$db = getDbConnection();

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$locations = $db->query("SELECT * FROM locations WHERE is_active = 1 ORDER BY name")->fetchAll();
$districtLocations = $db->query("SELECT * FROM locations WHERE is_active = 1 AND type = 'district' ORDER BY name")->fetchAll();
$priorities = $db->query("SELECT * FROM trip_priorities ORDER BY name")->fetchAll();

function plannerColumnExists(PDO $db, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $db->query("SHOW COLUMNS FROM `$table`");
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }

    foreach ($stmt->fetchAll() as $row) {
        if (($row['Field'] ?? '') === $column) {
            $cache[$key] = true;
            return true;
        }
    }

    $cache[$key] = false;
    return false;
}

$hotels = $db->query('SELECT * FROM hotels WHERE is_active = 1 ORDER BY ' . (plannerColumnExists($db, 'hotels', 'rating') ? 'rating DESC, ' : '') . 'price_per_night_lkr')->fetchAll();
$vehicles = $db->query('SELECT * FROM vehicles WHERE is_active = 1 ORDER BY ' . (plannerColumnExists($db, 'vehicles', 'rating') ? 'rating DESC, ' : '') . 'rate_per_day_lkr')->fetchAll();
$guides = $db->query('SELECT * FROM guides WHERE is_active = 1 ORDER BY ' . (plannerColumnExists($db, 'guides', 'rating') ? 'rating DESC, ' : '') . 'daily_rate_lkr')->fetchAll();
$drivers = $db->query('SELECT * FROM drivers WHERE is_active = 1 ORDER BY ' . (plannerColumnExists($db, 'drivers', 'rating') ? 'rating DESC, ' : '') . 'daily_rate_lkr')->fetchAll();

$result = null;
$savedPlanId = null;
$errors = [];
$plannerState = $_SESSION['trip_planner_state'] ?? [];
$step = 1;
$resultFromSession = $_SESSION['trip_planner_last_result'] ?? null;
if ($resultFromSession) {
    $result = $resultFromSession;
}

$plannerSuggestions = [];
$plannerInterestFlags = array_values(array_filter(array_map(function ($item): string {
    return strtolower(trim((string) $item));
}, $plannerState['interests'] ?? [])));
if (in_array('beaches', $plannerInterestFlags, true) || !empty($plannerState['include_beaches'])) {
    $plannerSuggestions[] = ['title' => 'Coastal escape', 'summary' => 'Blend beach hubs with slow evenings and seafood stops.'];
}
if (in_array('hill-country', $plannerInterestFlags, true) || !empty($plannerState['include_hidden_gems'])) {
    $plannerSuggestions[] = ['title' => 'Highland tea trail', 'summary' => 'Link cool-weather towns, tea estates and scenic viewpoints.'];
}
if (in_array('wildlife', $plannerInterestFlags, true) || in_array('culture', $plannerInterestFlags, true)) {
    $plannerSuggestions[] = ['title' => 'Culture and safari loop', 'summary' => 'Combine heritage stops with wildlife or park-based adventures.'];
}
if (!$plannerSuggestions) {
    $plannerSuggestions = [
        ['title' => 'Balanced discovery', 'summary' => 'Mix iconic spots, a food stop and one slower scenic day.'],
        ['title' => 'Scenic road trip', 'summary' => 'Use your selected towns as anchors and let the route breathe.'],
        ['title' => 'Classic first-time loop', 'summary' => 'Start with must-see highlights then add a hidden-gem detour.'],
    ];
}

function triggerPhotoScraper(string $what = 'destinations'): void
{
    $script = __DIR__ . '/scraper/fetch_photos.py';
    if (!file_exists($script)) {
        return;
    }

    $candidates = [];
    if (PHP_OS_FAMILY === 'Windows') {
        $candidates[] = ['py', ['-3']];
        $candidates[] = ['python', []];
    } else {
        $candidates[] = ['python3', []];
        $candidates[] = ['python', []];
    }

    $command = null;
    foreach ($candidates as $candidate) {
        $exe = $candidate[0];
        $args = $candidate[1];
        $versionCheck = escapeshellcmd($exe) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' --version 2>NUL';
        @exec($versionCheck, $out, $code);
        if ($code === 0) {
            $parts = [escapeshellcmd($exe)];
            foreach ($args as $arg) {
                $parts[] = escapeshellarg($arg);
            }
            $parts[] = escapeshellarg($script);
            $parts[] = '--what';
            $parts[] = escapeshellarg($what);
            $parts[] = '--limit';
            $parts[] = '3';
            $command = implode(' ', $parts);
            break;
        }
    }

    if ($command === null) {
        return;
    }

    $logPath = __DIR__ . '/assets/img/photo-sync.log';
    @exec($command . ' >> ' . escapeshellarg($logPath) . ' 2>&1', $out, $code);
}

triggerPhotoScraper();

function plannerSelectedValue($currentValue, $candidate): string
{
    if (is_array($currentValue)) {
        return in_array($candidate, $currentValue, true) ? 'selected' : '';
    }

    return ((string)$currentValue === (string)$candidate) ? 'selected' : '';
}

function plannerCheckedValue($currentValue, $candidate): string
{
    if (is_array($currentValue)) {
        return in_array($candidate, $currentValue, true) ? 'checked' : '';
    }

    return ((string)$currentValue === (string)$candidate) ? 'checked' : '';
}

function plannerLocationName(array $locations, ?int $id): string
{
    if ($id === null) {
        return 'Not selected';
    }

    foreach ($locations as $location) {
        if ((int)$location['id'] === $id) {
            return $location['name'];
        }
    }

    return 'Selected location';
}

function plannerPhotoForRecord(?string $imageRef, ?string $label = null): ?string
{
    $ref = trim((string)($imageRef ?? ''));
    if ($ref !== '') {
        if (str_starts_with($ref, 'assets/')) {
            return asset(ltrim($ref, '/'));
        }
        if (str_starts_with($ref, '/assets/')) {
            return $ref;
        }
        if (preg_match('#^(https?:)?//#i', $ref) || $ref[0] === '/') {
            return $ref;
        }
        return asset('img/' . ltrim($ref, '/'));
    }

    $label = trim((string)$label);
    if ($label === '') {
        return null;
    }

    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($label));
    $slug = trim((string)$slug, '-');
    if ($slug === '') {
        return null;
    }

    $extensions = ['.jpg', '.jpeg', '.png', '.webp', '.gif'];
    foreach ($extensions as $ext) {
        $path = __DIR__ . '/assets/img/' . $slug . $ext;
        if (file_exists($path)) {
            return asset('img/' . $slug . $ext);
        }
    }

    return null;
}

function plannerPhotoForLabel(?string $label): ?string
{
    return plannerPhotoForRecord(null, $label);
}

function plannerServiceMatchesDistrict(array $service, string $districtName, string $districtRegion): bool
{
    $text = strtolower(trim(($service['district'] ?? '') . ' ' . ($service['region'] ?? '') . ' ' . ($service['name'] ?? '')));
    $district = strtolower($districtName);
    $region = strtolower($districtRegion);
    return str_contains($text, $district) || str_contains($text, $region);
}

function plannerRouteSvg(array $items): string
{
    $points = [];
    foreach ($items as $item) {
        $dest = $item['destination'] ?? null;
        if (!$dest) {
            continue;
        }
        $lat = isset($dest['latitude']) ? (float)$dest['latitude'] : null;
        $lng = isset($dest['longitude']) ? (float)$dest['longitude'] : null;
        if ($lat === null || $lng === null) {
            continue;
        }
        $points[] = ['lat' => $lat, 'lng' => $lng, 'name' => $dest['name'] ?? 'Stop'];
    }

    if (count($points) < 2) {
        return '<div class="text-muted small">Add a few destination stops to preview a route map.</div>';
    }

    $lats = array_column($points, 'lat');
    $lngs = array_column($points, 'lng');
    $minLat = min($lats) - 0.25;
    $maxLat = max($lats) + 0.25;
    $minLng = min($lngs) - 0.25;
    $maxLng = max($lngs) + 0.25;

    $coords = [];
    foreach ($points as $point) {
        $x = 50 + (($point['lng'] - $minLng) / ($maxLng - $minLng)) * 500;
        $y = 250 - (($point['lat'] - $minLat) / ($maxLat - $minLat)) * 200;
        $coords[] = ['x' => round($x, 1), 'y' => round($y, 1)];
    }

    $polyline = implode(' ', array_map(fn($p) => $p['x'] . ',' . $p['y'], $coords));
    $markers = '';
    foreach ($coords as $index => $coord) {
        $markers .= '<circle cx="' . $coord['x'] . '" cy="' . $coord['y'] . '" r="7" fill="#f4b942" stroke="#fff" stroke-width="3"></circle>';
        if ($index < count($points)) {
            $markers .= '<circle cx="' . $coord['x'] . '" cy="' . $coord['y'] . '" r="18" fill="rgba(244,185,66,0.16)"></circle>';
        }
    }

    return '<svg viewBox="0 0 600 320" role="img" aria-label="Generated route map"><path d="M' . $polyline . '" stroke="#2d8f71" stroke-width="8" fill="none" stroke-linecap="round"></path>' . $markers . '</svg>';
}

function plannerApplyRouteProfile(array $items, string $profile, array $manualOrder = []): array
{
    if ($manualOrder) {
        $manual = [];
        foreach ($manualOrder as $destinationId => $slot) {
            $manual[(int)$destinationId] = (int)$slot;
        }

        usort($items, function ($a, $b) use ($manual): int {
            $idA = (int)($a['destination']['id'] ?? 0);
            $idB = (int)($b['destination']['id'] ?? 0);
            $slotA = $manual[$idA] ?? PHP_INT_MAX;
            $slotB = $manual[$idB] ?? PHP_INT_MAX;
            if ($slotA !== $slotB) {
                return $slotA <=> $slotB;
            }
            return ((int)($a['day'] ?? 0)) <=> ((int)($b['day'] ?? 0));
        });
    } else {
        $profile = in_array($profile, ['coastal', 'hillcountry', 'culture'], true) ? $profile : 'balanced';
        usort($items, function ($a, $b) use ($profile): int {
            $scoreA = plannerRoutePreferenceScore($a['destination'] ?? [], $profile);
            $scoreB = plannerRoutePreferenceScore($b['destination'] ?? [], $profile);
            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }
            return ((float)($b['score'] ?? 0)) <=> ((float)($a['score'] ?? 0));
        });
    }

    foreach ($items as $index => &$item) {
        $item['day'] = $index + 1;
    }
    unset($item);

    return $items;
}

function plannerRoutePreferenceScore(array $destination, string $profile): int
{
    $text = strtolower(trim(($destination['name'] ?? '') . ' ' . ($destination['short_description'] ?? '') . ' ' . ($destination['region'] ?? '') ));
    if ($profile === 'coastal') {
        return (int)(str_contains($text, 'beach') || str_contains($text, 'coast') || str_contains($text, 'surf') || str_contains($text, 'sea')) * 10;
    }
    if ($profile === 'hillcountry') {
        return (int)(str_contains($text, 'hill') || str_contains($text, 'tea') || str_contains($text, 'ella') || str_contains($text, 'nuwara') || str_contains($text, 'kandy')) * 10;
    }
    if ($profile === 'culture') {
        return (int)(str_contains($text, 'temple') || str_contains($text, 'fort') || str_contains($text, 'sacred') || str_contains($text, 'heritage') || str_contains($text, 'ancient')) * 10;
    }
    return 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['route_action'])) {
        $routeProfile = $_POST['route_profile'] ?? 'balanced';
        $manualOrder = [];
        foreach ($_POST['manual_route_order'] ?? [] as $destinationId => $slot) {
            $id = (int)$destinationId;
            $order = (int)$slot;
            if ($id > 0 && $order > 0) {
                $manualOrder[$id] = $order;
            }
        }
        if ($resultFromSession && is_array($resultFromSession)) {
            $resultFromSession['items'] = plannerApplyRouteProfile($resultFromSession['items'] ?? [], $routeProfile, $manualOrder);
            $_SESSION['trip_planner_last_result'] = $resultFromSession;
            $result = $resultFromSession;
        }
    }
    $step = max(1, min(6, (int)($_POST['planner_step'] ?? 1)));
    $action = $_POST['action'] ?? 'next';

    $incoming = $_POST;
    unset($incoming['planner_step'], $incoming['action']);
    $plannerState = array_replace($plannerState, $incoming);
    $_SESSION['trip_planner_state'] = $plannerState;

    if ($action === 'back') {
        $step = max(1, $step - 1);
    } elseif ($step >= 6) {
        $interests = array_values(array_filter(array_map('trim', $plannerState['interests'] ?? [])));
        $budget = in_array($plannerState['budget'] ?? 'medium', ['low','medium','high'], true) ? $plannerState['budget'] : 'medium';
        $days = max(1, min(21, (int)($plannerState['days'] ?? 5)));
        $travelers = max(1, min(40, (int)($plannerState['travelers'] ?? 2)));
        $style = trim((string)($plannerState['travel_style'] ?? ''));
        $startingLocationId = !empty($plannerState['starting_location_id']) ? (int)$plannerState['starting_location_id'] : null;
        $mainDestinationId = !empty($plannerState['main_destination_id']) ? (int)$plannerState['main_destination_id'] : null;
        $selectedTowns = array_values(array_filter(array_map('intval', $plannerState['towns'] ?? [])));
        $selectedDistricts = array_values(array_filter(array_map('intval', $plannerState['districts'] ?? [])));
        $includePopular = !empty($plannerState['include_popular']);
        $includeHiddenGems = !empty($plannerState['include_hidden_gems']);
        $priorityId = !empty($plannerState['priority_id']) ? (int)$plannerState['priority_id'] : null;

        if (empty($interests)) {
            $interests = ['culture', 'beaches', 'hill-country', 'wildlife'];
        }

        if ($days < 1 || $days > 21) {
            $errors[] = 'Trip length must be between 1 and 21 days.';
        }

        if (!$errors) {
            $recommender = new TripRecommender($db);
            $result = $recommender->recommend(
                $interests,
                $budget,
                $days,
                $style ?: null,
                $startingLocationId,
                $mainDestinationId,
                array_values(array_unique(array_merge($selectedTowns, $selectedDistricts))),
                $includePopular,
                $includeHiddenGems,
                $priorityId,
                $travelers,
                array_values(array_filter(array_map('intval', $plannerState['selected_places'] ?? [])))
            );

            try {
                $visitorId = isLoggedIn() && currentUser()['role'] === 'customer' ? (int)currentUser()['id'] : null;
                $savedPlanId = $recommender->savePlan($result, $visitorId, 'My Sri Lanka Trip');
                $_SESSION['trip_planner_last_result'] = $result;

                $choiceStmt = $db->prepare(
                    'INSERT INTO trip_plan_choices (trip_plan_id, choice_type, choice_key, choice_value, label, sort_order) VALUES (?,?,?,?,?,?)'
                );
                $serviceStmt = $db->prepare(
                    'INSERT INTO trip_plan_services (trip_plan_id, service_type, resource_id, resource_label, price_lkr, details, is_selected) VALUES (?,?,?,?,?,?,?)'
                );

                $districtLabels = [];
                foreach ($districtLocations as $district) {
                    $districtLabels[(int)$district['id']] = $district['name'];
                }
                $locationLabels = [];
                foreach ($locations as $location) {
                    $locationLabels[(int)$location['id']] = $location['name'];
                }

                $sort = 0;
                foreach ($selectedDistricts as $districtId) {
                    $choiceStmt->execute([$savedPlanId, 'district', 'district', (string)$districtId, $districtLabels[$districtId] ?? 'District', $sort++]);
                }
                foreach ($selectedTowns as $townId) {
                    $choiceStmt->execute([$savedPlanId, 'town', 'town', (string)$townId, $locationLabels[$townId] ?? 'Town', $sort++]);
                }
                foreach (($plannerState['selected_places'] ?? []) as $placeId) {
                    $placeId = (int)$placeId;
                    $placeName = $db->query('SELECT name FROM destinations WHERE id = ' . (int)$placeId)->fetchColumn();
                    $choiceStmt->execute([$savedPlanId, 'destination', 'place', (string)$placeId, $placeName ?: 'Destination', $sort++]);
                }
                foreach (($plannerState['meal_preferences'] ?? []) as $mealPreference) {
                    $choiceStmt->execute([$savedPlanId, 'meal', 'meal', (string)$mealPreference, (string)$mealPreference, $sort++]);
                }
                $choiceStmt->execute([$savedPlanId, 'traveler_type', 'traveler_type', (string)($plannerState['traveler_type'] ?? ''), (string)($plannerState['traveler_type'] ?? ''), $sort++]);

                $hotelId = !empty($plannerState['hotel_id']) ? (int)$plannerState['hotel_id'] : null;
                if ($hotelId) {
                    $hotelRow = $db->query('SELECT id, name, price_per_night_lkr FROM hotels WHERE id = ' . $hotelId)->fetch();
                    if ($hotelRow) {
                        $serviceStmt->execute([$savedPlanId, 'hotel', (int)$hotelRow['id'], $hotelRow['name'], (float)$hotelRow['price_per_night_lkr'], 'Selected hotel for the trip', 1]);
                    }
                }

                $vehicleId = !empty($plannerState['vehicle_id']) ? (int)$plannerState['vehicle_id'] : null;
                if ($vehicleId) {
                    $vehicleRow = $db->query('SELECT id, name, rate_per_day_lkr FROM vehicles WHERE id = ' . $vehicleId)->fetch();
                    if ($vehicleRow) {
                        $serviceStmt->execute([$savedPlanId, 'vehicle', (int)$vehicleRow['id'], $vehicleRow['name'], (float)$vehicleRow['rate_per_day_lkr'], 'Vehicle option selected for transport', 1]);
                    }
                }

                $guideId = !empty($plannerState['guide_id']) ? (int)$plannerState['guide_id'] : null;
                if ($guideId) {
                    $guideRow = $db->query('SELECT id, full_name, daily_rate_lkr FROM guides WHERE id = ' . $guideId)->fetch();
                    if ($guideRow) {
                        $serviceStmt->execute([$savedPlanId, 'guide', (int)$guideRow['id'], $guideRow['full_name'], (float)$guideRow['daily_rate_lkr'], 'Professional guide selected', 1]);
                    }
                }

                $driverId = !empty($plannerState['driver_id']) ? (int)$plannerState['driver_id'] : null;
                if ($driverId) {
                    $driverRow = $db->query('SELECT id, full_name, daily_rate_lkr FROM drivers WHERE id = ' . $driverId)->fetch();
                    if ($driverRow) {
                        $serviceStmt->execute([$savedPlanId, 'driver', (int)$driverRow['id'], $driverRow['full_name'], (float)$driverRow['daily_rate_lkr'], 'Driver selected for transfer service', 1]);
                    }
                }
            } catch (Throwable $e) {
                error_log('plan save failed: ' . $e->getMessage());
            }
        }
    } else {
        $step++;
    }
}

$selectedPlaces = array_values(array_filter(array_map('intval', $plannerState['selected_places'] ?? [])));
$selectedDistricts = array_values(array_filter(array_map('intval', $plannerState['districts'] ?? [])));
$selectedTowns = array_values(array_filter(array_map('intval', $plannerState['towns'] ?? [])));
$selectedMealtimes = array_values(array_filter(array_map('trim', $plannerState['meal_preferences'] ?? [])));

$districtLookup = [];
foreach ($districtLocations as $district) {
    $districtLookup[(int)$district['id']] = $district;
}

$selectedDistrictNames = [];
foreach ($selectedDistricts as $districtId) {
    $districtRow = $districtLookup[$districtId] ?? null;
    if ($districtRow) {
        $selectedDistrictNames[] = $districtRow['name'];
    }
}
$selectedDistrictNames = array_values(array_unique($selectedDistrictNames));

$placeRows = [];
if ($selectedDistrictNames || $selectedTowns) {
    $conditions = [];
    $params = [];
    if ($selectedDistrictNames) {
        $placeholders = implode(',', array_fill(0, count($selectedDistrictNames), '?'));
        $conditions[] = "district IN ($placeholders)";
        $params = array_merge($params, $selectedDistrictNames);
    }
    if ($selectedTowns) {
        $placeholders = implode(',', array_fill(0, count($selectedTowns), '?'));
        $conditions[] = "location_id IN ($placeholders)";
        $params = array_merge($params, $selectedTowns);
    }

    $sql = 'SELECT * FROM destinations WHERE is_active = 1';
    if ($conditions) {
        $sql .= ' AND (' . implode(' OR ', $conditions) . ')';
    }
    $sql .= plannerColumnExists($db, 'destinations', 'rating') ? ' ORDER BY rating DESC' : '';
    $placeStmt = $db->prepare($sql);
    $placeStmt->execute($params);
    $placeRows = $placeStmt->fetchAll();
} else {
    $placeRows = $db->query('SELECT * FROM destinations WHERE is_active = 1' . (plannerColumnExists($db, 'destinations', 'rating') ? ' ORDER BY rating DESC' : '') . ' LIMIT 20')->fetchAll();
}

$districtPlaceGroups = [];
if ($selectedDistrictNames) {
    foreach ($selectedDistrictNames as $districtName) {
        $groupPlaces = array_values(array_filter($placeRows, function (array $place) use ($districtName): bool {
            return (string)($place['district'] ?? '') === $districtName;
        }));
        if ($groupPlaces) {
            $districtPlaceGroups[] = ['name' => $districtName, 'places' => $groupPlaces];
        }
    }
}
if (!$districtPlaceGroups) {
    $districtPlaceGroups[] = ['name' => 'Popular highlights', 'places' => array_slice($placeRows, 0, 6)];
}

$allHotels = $db->query('SELECT * FROM hotels WHERE is_active = 1 ORDER BY ' . (plannerColumnExists($db, 'hotels', 'rating') ? 'rating DESC, ' : '') . 'price_per_night_lkr')->fetchAll();
$allVehicles = $db->query('SELECT * FROM vehicles WHERE is_active = 1 ORDER BY ' . (plannerColumnExists($db, 'vehicles', 'rating') ? 'rating DESC, ' : '') . 'rate_per_day_lkr')->fetchAll();
$allGuides = $db->query('SELECT * FROM guides WHERE is_active = 1 ORDER BY ' . (plannerColumnExists($db, 'guides', 'rating') ? 'rating DESC, ' : '') . 'daily_rate_lkr')->fetchAll();
$allDrivers = $db->query('SELECT * FROM drivers WHERE is_active = 1 ORDER BY ' . (plannerColumnExists($db, 'drivers', 'rating') ? 'rating DESC, ' : '') . 'daily_rate_lkr')->fetchAll();

$districtServiceGroups = [];
foreach ($selectedDistricts as $districtId) {
    $districtRow = $districtLookup[$districtId] ?? null;
    if (!$districtRow) {
        continue;
    }

    $districtName = $districtRow['name'];
    $districtRegion = $districtRow['region'];
    $serviceGroup = [
        'name' => $districtName,
        'region' => $districtRegion,
        'hotels' => [],
        'vehicles' => [],
        'guides' => [],
        'drivers' => [],
    ];

    foreach ($allHotels as $hotel) {
        if (plannerServiceMatchesDistrict($hotel, $districtName, $districtRegion) && count($serviceGroup['hotels']) < 3) {
            $serviceGroup['hotels'][] = $hotel;
        }
    }
    foreach ($allVehicles as $vehicle) {
        if (plannerServiceMatchesDistrict($vehicle, $districtName, $districtRegion) && count($serviceGroup['vehicles']) < 3) {
            $serviceGroup['vehicles'][] = $vehicle;
        }
    }
    foreach ($allGuides as $guide) {
        if (plannerServiceMatchesDistrict($guide, $districtName, $districtRegion) && count($serviceGroup['guides']) < 3) {
            $serviceGroup['guides'][] = $guide;
        }
    }
    foreach ($allDrivers as $driver) {
        if (plannerServiceMatchesDistrict($driver, $districtName, $districtRegion) && count($serviceGroup['drivers']) < 3) {
            $serviceGroup['drivers'][] = $driver;
        }
    }

    if ($serviceGroup['hotels'] || $serviceGroup['vehicles'] || $serviceGroup['guides'] || $serviceGroup['drivers']) {
        $districtServiceGroups[] = $serviceGroup;
    }
}
if (!$districtServiceGroups) {
    $districtServiceGroups[] = [
        'name' => 'Island-wide options',
        'region' => 'Flexible',
        'hotels' => array_slice($allHotels, 0, 3),
        'vehicles' => array_slice($allVehicles, 0, 3),
        'guides' => array_slice($allGuides, 0, 3),
        'drivers' => array_slice($allDrivers, 0, 3),
    ];
}

$selectedHotel = null;
if (!empty($plannerState['hotel_id'])) {
    $selectedHotel = $db->query('SELECT * FROM hotels WHERE id = ' . (int)$plannerState['hotel_id'])->fetch();
}

$selectedVehicle = null;
if (!empty($plannerState['vehicle_id'])) {
    $selectedVehicle = $db->query('SELECT * FROM vehicles WHERE id = ' . (int)$plannerState['vehicle_id'])->fetch();
}

$selectedGuide = null;
if (!empty($plannerState['guide_id'])) {
    $selectedGuide = $db->query('SELECT * FROM guides WHERE id = ' . (int)$plannerState['guide_id'])->fetch();
}

$selectedDriver = null;
if (!empty($plannerState['driver_id'])) {
    $selectedDriver = $db->query('SELECT * FROM drivers WHERE id = ' . (int)$plannerState['driver_id'])->fetch();
}

include __DIR__ . '/includes/header.php';
?>

<section class="section-pad" style="padding-top:3rem; padding-bottom:4rem;">
  <div class="container">
    <div class="section-head text-center" style="max-width:980px; margin:0 auto 2rem;">
      <span class="eyebrow">Smart trip planning</span>
      <h2>Design your Sri Lanka journey with destination cards, stays, transport and curated meals</h2>
      <p>Choose your start point, districts, towns, attractions, hotels and transport with a smooth card-focused flow inspired by the sample experience.</p>
    </div>

    <div class="row g-4 align-items-start">
      <div class="col-lg-4">
        <div class="admin-card p-4 sticky-top" style="top:90px;">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <div class="eyebrow">Planner flow</div>
              <h4 class="mb-0">Step <?= (int)$step ?> of 6</h4>
            </div>
            <span class="badge bg-success-subtle text-success">Live proposal</span>
          </div>
          <div class="progress" style="height:8px;">
            <div class="progress-bar" role="progressbar" style="width:<?= round(($step / 6) * 100) ?>%"></div>
          </div>
          <div class="planner-step-list mt-4">
            <?php $stepLabels = ['Start & base', 'Places & regions', 'Interests & meals', 'Hotels & transport', 'Travelers & notes', 'Review & generate']; foreach ($stepLabels as $idx => $label): ?>
              <div class="planner-step-item <?= $idx + 1 === $step ? 'active' : '' ?>">
                <span class="step-num"><?= $idx + 1 ?></span>
                <span><?= e($label) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($result): ?>
            <a href="itinerary.php?plan=<?= (int)$savedPlanId ?>" class="btn lg-btn-cta w-100 mt-3"><i class="bi bi-arrow-right-circle"></i> Open plan</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-lg-8">
        <form method="post" class="planner-shell p-4 p-lg-4">
          <input type="hidden" name="planner_step" value="<?= (int)$step ?>">

          <div class="planner-choice-card mb-4">
            <div class="eyebrow">Suggested trip directions</div>
            <div class="row g-3 mt-1">
              <?php foreach (array_slice($plannerSuggestions, 0, 3) as $suggestion): ?>
                <div class="col-md-4">
                  <div class="border rounded-3 p-3 h-100">
                    <h6 class="mb-1"><?= e($suggestion['title']) ?></h6>
                    <div class="small text-muted"><?= e($suggestion['summary']) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <?php if ($step === 1): ?>
            <div class="mb-4">
              <label class="form-label fw-semibold"><span class="step-num">1</span>Start your journey</label>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small text-muted">Starting location</label>
                  <select name="starting_location_id" class="form-select">
                    <option value="">Not sure yet / arriving by air</option>
                    <?php foreach ($locations as $location): ?>
                      <option value="<?= (int)$location['id'] ?>" <?= plannerSelectedValue($plannerState['starting_location_id'] ?? null, $location['id']) ?>><?= e($location['name']) ?><?= $location['type'] === 'district' ? ' · District' : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-muted">Main destination / base</label>
                  <select name="main_destination_id" class="form-select">
                    <option value="">No single base — show me the island</option>
                    <?php foreach ($locations as $location): ?>
                      <option value="<?= (int)$location['id'] ?>" <?= plannerSelectedValue($plannerState['main_destination_id'] ?? null, $location['id']) ?>><?= e($location['name']) ?><?= $location['type'] === 'district' ? ' · District' : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold"><span class="step-num">2</span>Select districts and towns</label>
              <div class="mb-3">
                <label class="form-label small text-muted">Districts to explore</label>
                <div class="row g-3">
                  <?php foreach ($districtLocations as $district): $districtPhoto = plannerPhotoForLabel($district['name']); ?>
                    <div class="col-md-6">
                      <label class="planner-choice-card d-flex align-items-center gap-3">
                        <div class="planner-photo-card" style="<?= $districtPhoto ? 'background-image:url(' . e($districtPhoto) . ')' : '' ?>">
                          <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <div class="flex-grow-1">
                          <div class="fw-semibold"><?= e($district['name']) ?></div>
                          <div class="small text-muted">Great for a custom route base</div>
                        </div>
                        <input type="checkbox" name="districts[]" value="<?= (int)$district['id'] ?>" <?= plannerCheckedValue($plannerState['districts'] ?? [], $district['id']) ?>>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <label class="form-label small text-muted">Key towns / cities to include</label>
              <div class="row g-3">
                <?php foreach ($locations as $location): if ($location['type'] === 'district') continue; $townPhoto = plannerPhotoForLabel($location['name']); ?>
                  <div class="col-md-6">
                    <label class="planner-choice-card d-flex align-items-center gap-3">
                      <div class="planner-photo-card" style="<?= $townPhoto ? 'background-image:url(' . e($townPhoto) . ')' : '' ?>">
                        <i class="bi bi-pin-map-fill"></i>
                      </div>
                      <div class="flex-grow-1">
                        <div class="fw-semibold"><?= e($location['name']) ?></div>
                        <div class="small text-muted"><?= e($location['region']) ?></div>
                      </div>
                      <input type="checkbox" name="towns[]" value="<?= (int)$location['id'] ?>" <?= plannerCheckedValue($plannerState['towns'] ?? [], $location['id']) ?>>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php elseif ($step === 2): ?>
            <div class="mb-4">
              <label class="form-label fw-semibold"><span class="step-num">1</span>Places you'd like to visit</label>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <label class="interest-chip">
                  <input type="checkbox" name="include_popular" value="1" <?= !empty($plannerState['include_popular']) ? 'checked' : '' ?>>
                  <i class="bi bi-star"></i> Popular attractions
                </label>
                <label class="interest-chip">
                  <input type="checkbox" name="include_hidden_gems" value="1" <?= !empty($plannerState['include_hidden_gems']) ? 'checked' : '' ?>>
                  <i class="bi bi-gem"></i> Hidden gems
                </label>
                <label class="interest-chip">
                  <input type="checkbox" name="include_beaches" value="1" <?= !empty($plannerState['include_beaches']) ? 'checked' : '' ?>>
                  <i class="bi bi-water"></i> Beach / coast side
                </label>
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold"><span class="step-num">2</span>Pick destinations for the route</label>
              <div class="row g-3 mt-2">
                <?php foreach ($districtPlaceGroups as $group): $groupPhoto = plannerPhotoForLabel($group['name']); ?>
                  <div class="col-12">
                    <div class="planner-choice-card p-3">
                      <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                          <div class="eyebrow">District focus</div>
                          <h6 class="mb-1"><?= e($group['name']) ?></h6>
                          <div class="small text-muted">Suggested places to visit in this district</div>
                        </div>
                        <?php if ($groupPhoto): ?><img src="<?= e($groupPhoto) ?>" alt="<?= e($group['name']) ?>" class="rounded" style="width:88px;height:60px;object-fit:cover"><?php endif; ?>
                      </div>
                      <div class="row g-3">
                        <?php foreach ($group['places'] as $place): $placePhoto = plannerPhotoForRecord($place['image_url'] ?? null, $place['name']); ?>
                          <div class="col-md-6">
                            <label class="planner-choice-card d-flex align-items-start gap-2" style="cursor:pointer;">
                              <input type="checkbox" name="selected_places[]" value="<?= (int)$place['id'] ?>" <?= plannerCheckedValue($selectedPlaces, $place['id']) ?>>
                              <span class="flex-grow-1">
                                <strong><?= e($place['name']) ?></strong><br>
                                <span class="small text-muted"><?= e($place['district']) ?> · <?= e($place['region']) ?></span>
                                <?php if ($placePhoto): ?><img src="<?= e($placePhoto) ?>" alt="<?= e($place['name']) ?>" class="rounded mt-2" style="width:100%;height:90px;object-fit:cover"><?php endif; ?>
                              </span>
                            </label>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php elseif ($step === 3): ?>
            <div class="mb-4">
              <label class="form-label fw-semibold"><span class="step-num">1</span>What are you interested in?</label>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <?php foreach ($categories as $category): ?>
                  <label class="interest-chip">
                    <input type="checkbox" name="interests[]" value="<?= e($category['slug']) ?>" <?= plannerCheckedValue($plannerState['interests'] ?? [], $category['slug']) ?>>
                    <i class="bi <?= e($category['icon'] ?: 'bi-geo') ?>"></i> <?= e($category['name']) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold"><span class="step-num">2</span>Meal preferences</label>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <?php $mealOptions = ['Local Sri Lankan', 'Seafood', 'Vegetarian', 'Vegan', 'Halal', 'Breakfast buffet']; foreach ($mealOptions as $meal): ?>
                  <label class="interest-chip">
                    <input type="checkbox" name="meal_preferences[]" value="<?= e($meal) ?>" <?= plannerCheckedValue($selectedMealtimes, $meal) ?>>
                    <i class="bi bi-cup-hot"></i> <?= e($meal) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold"><span class="step-num">3</span>Budget</label>
                <select name="budget" class="form-select">
                  <option value="low" <?= plannerSelectedValue($plannerState['budget'] ?? 'medium', 'low') ?>>Low — friendly budget</option>
                  <option value="medium" <?= plannerSelectedValue($plannerState['budget'] ?? 'medium', 'medium') ?>>Medium — comfortable mid-range</option>
                  <option value="high" <?= plannerSelectedValue($plannerState['budget'] ?? 'medium', 'high') ?>>High — premium / resort</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold"><span class="step-num">4</span>Trip length (days)</label>
                <input type="number" name="days" min="1" max="21" class="form-control" value="<?= e((string)($plannerState['days'] ?? 5)) ?>">
              </div>
            </div>
          <?php elseif ($step === 4): ?>
            <div class="mb-4">
              <label class="form-label fw-semibold"><span class="step-num">1</span>Choose stays near each district</label>
              <div class="row g-3 mt-2">
                <?php foreach ($districtServiceGroups as $group): ?>
                  <div class="col-12">
                    <div class="planner-choice-card p-3">
                      <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                          <div class="eyebrow">District stay ideas</div>
                          <h6 class="mb-1"><?= e($group['name']) ?></h6>
                          <div class="small text-muted"><?= e($group['region']) ?></div>
                        </div>
                      </div>
                      <?php if ($group['hotels']): ?>
                        <div class="mb-3">
                          <div class="small fw-semibold text-muted mb-2">Hotels</div>
                          <div class="row g-3">
                            <?php foreach ($group['hotels'] as $hotel): $hotelPhoto = plannerPhotoForRecord($hotel['image_url'] ?? null, $hotel['name']); ?>
                              <div class="col-md-6">
                                <label class="planner-choice-card d-flex justify-content-between align-items-start gap-2" style="cursor:pointer;">
                                  <span class="flex-grow-1">
                                    <strong><?= e($hotel['name']) ?></strong><br>
                                    <span class="small text-muted"><?= e($hotel['district'] ?: $group['name']) ?> · <?= e($hotel['region']) ?></span><br>
                                    <span class="small text-muted">From LKR <?= number_format((float)$hotel['price_per_night_lkr'],0) ?> / night</span>
                                    <?php if ($hotelPhoto): ?><img src="<?= e($hotelPhoto) ?>" alt="<?= e($hotel['name']) ?>" class="rounded mt-2" style="width:100%;height:90px;object-fit:cover"><?php endif; ?>
                                  </span>
                                  <input type="radio" name="hotel_id" value="<?= (int)$hotel['id'] ?>" <?= plannerSelectedValue($plannerState['hotel_id'] ?? null, $hotel['id']) ?>>
                                </label>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        </div>
                      <?php endif; ?>
                      <?php if ($group['vehicles'] || $group['guides'] || $group['drivers']): ?>
                        <div class="row g-3">
                          <?php if ($group['vehicles']): ?>
                            <div class="col-md-6">
                              <div class="small fw-semibold text-muted mb-2">Vehicles</div>
                              <?php foreach ($group['vehicles'] as $vehicle): $vehiclePhoto = plannerPhotoForRecord($vehicle['image_url'] ?? null, $vehicle['name']); ?>
                                <label class="planner-choice-card d-flex justify-content-between align-items-start gap-2 mb-2" style="cursor:pointer;">
                                  <span class="flex-grow-1">
                                    <strong><?= e($vehicle['name']) ?></strong><br>
                                    <span class="small text-muted">Seats: <?= (int)$vehicle['seats'] ?> · <?= e($vehicle['region']) ?></span><br>
                                    <span class="small text-muted">LKR <?= number_format((float)$vehicle['rate_per_day_lkr'],0) ?> / day</span>
                                    <?php if ($vehiclePhoto): ?><img src="<?= e($vehiclePhoto) ?>" alt="<?= e($vehicle['name']) ?>" class="rounded mt-2" style="width:100%;height:80px;object-fit:cover"><?php endif; ?>
                                  </span>
                                  <input type="radio" name="vehicle_id" value="<?= (int)$vehicle['id'] ?>" <?= plannerSelectedValue($plannerState['vehicle_id'] ?? null, $vehicle['id']) ?>>
                                </label>
                              <?php endforeach; ?>
                            </div>
                          <?php endif; ?>
                          <?php if ($group['guides'] || $group['drivers']): ?>
                            <div class="col-md-6">
                              <div class="small fw-semibold text-muted mb-2">Local hosts</div>
                              <?php if ($group['guides']): foreach ($group['guides'] as $guide): $guidePhoto = plannerPhotoForRecord($guide['photo_url'] ?? null, $guide['full_name']); ?>
                                <label class="planner-choice-card d-flex justify-content-between align-items-start gap-2 mb-2" style="cursor:pointer;">
                                  <span class="flex-grow-1">
                                    <strong><?= e($guide['full_name']) ?></strong><br>
                                    <span class="small text-muted">Guide · <?= e($guide['region']) ?></span><br>
                                    <span class="small text-muted">LKR <?= number_format((float)$guide['daily_rate_lkr'],0) ?> / day</span>
                                    <?php if ($guidePhoto): ?><img src="<?= e($guidePhoto) ?>" alt="<?= e($guide['full_name']) ?>" class="rounded mt-2" style="width:100%;height:80px;object-fit:cover"><?php endif; ?>
                                  </span>
                                  <input type="radio" name="guide_id" value="<?= (int)$guide['id'] ?>" <?= plannerSelectedValue($plannerState['guide_id'] ?? null, $guide['id']) ?>>
                                </label>
                              <?php endforeach; endif; ?>
                              <?php if ($group['drivers']): foreach ($group['drivers'] as $driver): $driverPhoto = plannerPhotoForRecord($driver['photo_url'] ?? null, $driver['full_name']); ?>
                                <label class="planner-choice-card d-flex justify-content-between align-items-start gap-2 mb-2" style="cursor:pointer;">
                                  <span class="flex-grow-1">
                                    <strong><?= e($driver['full_name']) ?></strong><br>
                                    <span class="small text-muted">Driver · <?= e($driver['region']) ?></span><br>
                                    <span class="small text-muted">LKR <?= number_format((float)$driver['daily_rate_lkr'],0) ?> / day</span>
                                    <?php if ($driverPhoto): ?><img src="<?= e($driverPhoto) ?>" alt="<?= e($driver['full_name']) ?>" class="rounded mt-2" style="width:100%;height:80px;object-fit:cover"><?php endif; ?>
                                  </span>
                                  <input type="radio" name="driver_id" value="<?= (int)$driver['id'] ?>" <?= plannerSelectedValue($plannerState['driver_id'] ?? null, $driver['id']) ?>>
                                </label>
                              <?php endforeach; endif; ?>
                            </div>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php elseif ($step === 5): ?>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold"><span class="step-num">1</span>Traveler type / group size</label>
                <select name="traveler_type" class="form-select">
                  <option value="solo" <?= plannerSelectedValue($plannerState['traveler_type'] ?? 'couple', 'solo') ?>>Solo traveler</option>
                  <option value="couple" <?= plannerSelectedValue($plannerState['traveler_type'] ?? 'couple', 'couple') ?>>Couple</option>
                  <option value="family" <?= plannerSelectedValue($plannerState['traveler_type'] ?? 'couple', 'family') ?>>Family</option>
                  <option value="group" <?= plannerSelectedValue($plannerState['traveler_type'] ?? 'couple', 'group') ?>>Friends / group</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold"><span class="step-num">2</span>Travelers</label>
                <input type="number" name="travelers" min="1" max="40" class="form-control" value="<?= e((string)($plannerState['travelers'] ?? 2)) ?>">
              </div>
            </div>

            <div class="mt-4 mb-4">
              <label class="form-label fw-semibold"><span class="step-num">3</span>Travel style</label>
              <select name="travel_style" class="form-select">
                <option value="">No preference</option>
                <option value="relaxed" <?= plannerSelectedValue($plannerState['travel_style'] ?? '', 'relaxed') ?>>Relaxed / slow travel</option>
                <option value="adventurous" <?= plannerSelectedValue($plannerState['travel_style'] ?? '', 'adventurous') ?>>Adventurous / active</option>
                <option value="family" <?= plannerSelectedValue($plannerState['travel_style'] ?? '', 'family') ?>>Family friendly</option>
                <option value="romantic" <?= plannerSelectedValue($plannerState['travel_style'] ?? '', 'romantic') ?>>Romantic / honeymoon</option>
              </select>
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold"><span class="step-num">4</span>Notes for the planner</label>
              <textarea name="notes" class="form-control" rows="4" placeholder="Tell us about mobility needs, preferred pace, or special requests."><?= e((string)($plannerState['notes'] ?? '')) ?></textarea>
            </div>
          <?php else: ?>
            <div class="mb-4">
              <label class="form-label fw-semibold"><span class="step-num">1</span>Review your choices</label>
              <div class="planner-choice-card small text-muted">
                <div class="row g-3">
                  <div class="col-md-6">
                    <strong>Start</strong><br>
                    <?= e(plannerLocationName($locations, !empty($plannerState['starting_location_id']) ? (int)$plannerState['starting_location_id'] : null)) ?>
                  </div>
                  <div class="col-md-6">
                    <strong>Base</strong><br>
                    <?= e(plannerLocationName($locations, !empty($plannerState['main_destination_id']) ? (int)$plannerState['main_destination_id'] : null)) ?>
                  </div>
                  <div class="col-md-6">
                    <strong>Districts</strong><br>
                    <?= e(implode(', ', array_map(function ($id) use ($districtLocations) {
                        foreach ($districtLocations as $district) {
                            if ((int)$district['id'] === (int)$id) return $district['name'];
                        }
                        return (string)$id;
                    }, $selectedDistricts))) ?>
                  </div>
                  <div class="col-md-6">
                    <strong>Places</strong><br>
                    <?= e(count($selectedPlaces) ? count($selectedPlaces) . ' selected' : 'No specific places selected yet') ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold"><span class="step-num">2</span>Selected add-ons</label>
              <div class="planner-choice-card small text-muted">
                <?php if ($selectedHotel): ?><div>Hotel: <?= e($selectedHotel['name']) ?> · LKR <?= number_format((float)$selectedHotel['price_per_night_lkr'],0) ?>/night</div><?php endif; ?>
                <?php if ($selectedVehicle): ?><div>Vehicle: <?= e($selectedVehicle['name']) ?> · LKR <?= number_format((float)$selectedVehicle['rate_per_day_lkr'],0) ?>/day</div><?php endif; ?>
                <?php if ($selectedGuide): ?><div>Guide: <?= e($selectedGuide['full_name']) ?> · LKR <?= number_format((float)$selectedGuide['daily_rate_lkr'],0) ?>/day</div><?php endif; ?>
                <?php if ($selectedDriver): ?><div>Driver: <?= e($selectedDriver['full_name']) ?> · LKR <?= number_format((float)$selectedDriver['daily_rate_lkr'],0) ?>/day</div><?php endif; ?>
                <?php if (!$selectedHotel && !$selectedVehicle && !$selectedGuide && !$selectedDriver): ?><div>No add-ons selected yet.</div><?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>

          <div class="d-flex justify-content-between align-items-center gap-2 mt-4">
            <?php if ($step > 1): ?>
              <button type="submit" name="action" value="back" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</button>
            <?php else: ?>
              <span></span>
            <?php endif; ?>
            <?php if ($step < 6): ?>
              <button type="submit" name="action" value="next" class="btn lg-btn-cta"><i class="bi bi-arrow-right"></i> Continue</button>
            <?php else: ?>
              <button type="submit" name="action" value="next" class="btn lg-btn-cta"><i class="bi bi-magic"></i> Generate itinerary</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <?php if ($result): ?>
      <div class="row g-4 mt-3">
        <div class="col-lg-8">
          <div class="admin-card p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
              <div>
                <div class="eyebrow">Generated itinerary</div>
                <h3 class="mb-0">Your <?= (int)$result['meta']['days'] ?>-day route</h3>
              </div>
              <span class="badge bg-success-subtle text-success">Matched on <?= e(implode(', ', $result['meta']['interests'])) ?></span>
            </div>
            <div class="planner-map-preview mb-4" aria-label="Map preview of the suggested route">
              <?= plannerRouteSvg($result['items']) ?>
            </div>
            <form method="post" class="planner-choice-card mb-4">
              <input type="hidden" name="route_action" value="apply">
              <input type="hidden" name="planner_step" value="6">
              <div class="row g-3 align-items-end">
                <div class="col-md-5">
                  <label class="form-label small text-muted">Route style</label>
                  <select name="route_profile" class="form-select">
                    <option value="balanced">Balanced loop</option>
                    <option value="coastal">Coastal sweep</option>
                    <option value="hillcountry">Hill-country escape</option>
                    <option value="culture">Culture-first trail</option>
                  </select>
                </div>
                <div class="col-md-5">
                  <label class="form-label small text-muted">Manual order</label>
                  <div class="small text-muted">Set the order for each stop below.</div>
                </div>
                <div class="col-md-2">
                  <button type="submit" class="btn btn-outline-secondary w-100">Apply</button>
                </div>
              </div>
              <div class="mt-3">
                <?php foreach ($result['items'] as $idx => $item): $dest = $item['destination']; ?>
                  <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <span class="small fw-semibold"><?= e($dest['name']) ?></span>
                    <select name="manual_route_order[<?= (int)$dest['id'] ?>]" class="form-select form-select-sm" style="max-width:100px;">
                      <?php for ($slot = 1; $slot <= count($result['items']); $slot++): ?>
                        <option value="<?= $slot ?>" <?= ((int)$item['day'] ?? 0) === $slot ? 'selected' : '' ?>>Day <?= $slot ?></option>
                      <?php endfor; ?>
                    </select>
                  </div>
                <?php endforeach; ?>
              </div>
            </form>
            <?php foreach ($result['items'] as $item): $d = $item['destination']; ?>
              <div class="planner-choice-card mb-3">
                <div class="d-flex justify-content-between align-items-start gap-3">
                  <div>
                    <h5 class="mb-1"><a href="destination-details.php?slug=<?= urlencode($d['slug']) ?>" class="text-decoration-none" style="color:var(--ink-900)"><?= e($d['name']) ?></a></h5>
                    <p class="small text-muted mb-0"><?= e($d['region']) ?> · <?= e($d['short_description']) ?></p>
                  </div>
                  <span class="rating"><i class="bi bi-star-fill"></i> <?= number_format((float)$d['rating'],1) ?></span>
                </div>
                <div class="mt-2 d-flex justify-content-between align-items-center small text-muted">
                  <span>Match score <?= number_format((float)$item['score'],1) ?></span>
                  <span>Budget: <?= e($d['budget_tier']) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="admin-card p-4">
            <div class="eyebrow">Booking add-ons</div>
            <h4 class="mb-3">Suggested for your trip</h4>
            <?php if ($selectedHotel): ?>
              <div class="planner-choice-card mb-3">
                <strong><?= e($selectedHotel['name']) ?></strong><br>
                <span class="small text-muted">Hotel stay · LKR <?= number_format((float)$selectedHotel['price_per_night_lkr'],0) ?>/night</span>
              </div>
            <?php endif; ?>
            <?php if ($selectedVehicle): ?>
              <div class="planner-choice-card mb-3">
                <strong><?= e($selectedVehicle['name']) ?></strong><br>
                <span class="small text-muted">Vehicle rental · LKR <?= number_format((float)$selectedVehicle['rate_per_day_lkr'],0) ?>/day</span>
              </div>
            <?php endif; ?>
            <?php if ($selectedGuide): ?>
              <div class="planner-choice-card mb-3">
                <strong><?= e($selectedGuide['full_name']) ?></strong><br>
                <span class="small text-muted">Guide · LKR <?= number_format((float)$selectedGuide['daily_rate_lkr'],0) ?>/day</span>
              </div>
            <?php endif; ?>
            <?php if ($selectedDriver): ?>
              <div class="planner-choice-card mb-3">
                <strong><?= e($selectedDriver['full_name']) ?></strong><br>
                <span class="small text-muted">Driver · LKR <?= number_format((float)$selectedDriver['daily_rate_lkr'],0) ?>/day</span>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
