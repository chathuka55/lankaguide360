<?php
/**
 * TripRecommender — a lightweight, explainable rule-based scoring engine
 * for LankaGuide 360's intelligent trip planning feature.
 *
 * Approach:
 *  1. Score every active destination against the visitor's chosen interest
 *     categories using the destination_categories.weight relevance table.
 *  2. Apply a budget-fit modifier so trips stay within the stated tier.
 *  3. Apply a small regional-diversity penalty so the same region doesn't
 *     dominate every day of a longer trip (encourages a well-rounded route).
 *  4. Sort by score and greedily allocate the top destinations across the
 *     requested number of days (1 primary destination per day, capped by
 *     avg_visit_hours so a day never becomes unrealistic).
 *
 * This is intentionally transparent/rule-based (not a black-box ML model)
 * so results can be explained to the user and marked/evaluated in a
 * final-year project report — see README.md "Recommendation Logic".
 */

class TripRecommender
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param array $interestSlugs      e.g. ['beaches','wildlife']
     * @param string $budgetTier        'low' | 'medium' | 'high'
     * @param int $days
     * @param string|null $travelStyle
     * @param int|null $startingLocationId   locations.id — where the trip begins
     * @param int|null $mainDestinationId    locations.id — the primary base/area
     * @param array $citiesToVisit           locations.id[] — must-visit towns/cities
     * @param bool $includePopular           include destinations tagged 'popular'
     * @param bool $includeHiddenGems        include destinations tagged 'hidden_gem'
     * @param int|null $priorityId           trip_priorities.id — what matters most
     * @return array{items: array, meta: array}
     */
    public function recommend(
        array $interestSlugs,
        string $budgetTier,
        int $days,
        ?string $travelStyle = null,
        ?int $startingLocationId = null,
        ?int $mainDestinationId = null,
        array $citiesToVisit = [],
        bool $includePopular = true,
        bool $includeHiddenGems = true,
        ?int $priorityId = null,
        int $travelers = 2,
        array $preferredDestinationIds = []
    ): array {
        $days = max(1, min($days, 21));
        $travelers = max(1, min($travelers, 40));
        $interestSlugs = array_values(array_filter(array_unique($interestSlugs)));
        $citiesToVisit = array_values(array_filter(array_map('intval', $citiesToVisit)));
        $preferredDestinationIds = array_values(array_filter(array_map('intval', $preferredDestinationIds)));

        if (empty($interestSlugs)) {
            // Fall back to a balanced "greatest hits" set if no interests chosen
            $interestSlugs = ['culture', 'beaches', 'hill-country', 'wildlife'];
        }

        // If the visitor unchecked both popularity options, treat it as "no
        // preference" rather than returning zero destinations.
        if (!$includePopular && !$includeHiddenGems) {
            $includePopular = true;
            $includeHiddenGems = true;
        }
        $popularityTiers = [];
        if ($includePopular) $popularityTiers[] = 'popular';
        if ($includeHiddenGems) $popularityTiers[] = 'hidden_gem';

        $placeholders = implode(',', array_fill(0, count($interestSlugs), '?'));
        $popPlaceholders = implode(',', array_fill(0, count($popularityTiers), '?'));

        $sql = "SELECT d.*,
                       SUM(dc.weight) AS interest_score,
                       GROUP_CONCAT(DISTINCT c.name ORDER BY dc.weight DESC SEPARATOR ', ') AS matched_categories
                FROM destinations d
                JOIN destination_categories dc ON dc.destination_id = d.id
                JOIN categories c ON c.id = dc.category_id
                WHERE d.is_active = 1
                  AND c.slug IN ($placeholders)
                  AND d.popularity_tier IN ($popPlaceholders)
                GROUP BY d.id
                ORDER BY interest_score DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([...$interestSlugs, ...$popularityTiers]);
        $candidates = $stmt->fetchAll();

        if ($preferredDestinationIds) {
            $preferredPlaceholders = implode(',', array_fill(0, count($preferredDestinationIds), '?'));
            $preferredStmt = $this->db->prepare("SELECT * FROM destinations WHERE id IN ($preferredPlaceholders) AND is_active = 1");
            $preferredStmt->execute($preferredDestinationIds);
            $preferredDestinations = $preferredStmt->fetchAll();
            foreach ($preferredDestinations as $preferred) {
                $preferred['final_score'] = 100 + (int)($preferred['rating'] ?? 0) * 2;
                $preferred['preferred'] = true;
                $candidates = array_values(array_filter($candidates, fn($candidate) => (int)$candidate['id'] !== (int)$preferred['id']));
                array_unshift($candidates, $preferred);
            }
        }

        // Priority bonus: add weighted category scores from the visitor's
        // chosen trip priority (e.g. "Adventure & Thrills" boosts adventure/
        // wildlife-tagged destinations), scaled down so plain interests still
        // dominate the ranking.
        $priorityWeights = [];
        if ($priorityId) {
            $pStmt = $this->db->prepare(
                "SELECT c.slug, pc.weight FROM priority_categories pc
                 JOIN categories c ON c.id = pc.category_id WHERE pc.priority_id = ?"
            );
            $pStmt->execute([$priorityId]);
            foreach ($pStmt->fetchAll() as $row) {
                $priorityWeights[$row['slug']] = (int)$row['weight'];
            }
        }

        // Look up each candidate's own matched category slugs so we can apply
        // the priority bonus per destination.
        $destCatStmt = $this->db->prepare(
            "SELECT c.slug FROM destination_categories dc JOIN categories c ON c.id = dc.category_id WHERE dc.destination_id = ?"
        );

        // Look up destination regions for the selected starting/main locations,
        // used for the geographic relevance bonus below.
        $mainRegion = $mainDestinationId ? $this->regionForLocation($mainDestinationId) : null;
        $startRegion = $startingLocationId ? $this->regionForLocation($startingLocationId) : null;

        // Budget fit modifier: exact tier match = full score, adjacent = slight
        // penalty, opposite extreme = larger penalty. Keeps results flexible
        // rather than rigidly excluding destinations outside the tier.
        $budgetOrder = ['low' => 0, 'medium' => 1, 'high' => 2];
        foreach ($candidates as &$c) {
            $distance = abs($budgetOrder[$c['budget_tier']] - $budgetOrder[$budgetTier]);
            $budgetModifier = $distance === 0 ? 1.0 : ($distance === 1 ? 0.85 : 0.65);
            $score = $c['interest_score'] * $budgetModifier;

            if ($priorityWeights) {
                $destCatStmt->execute([$c['id']]);
                $destSlugs = $destCatStmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($destSlugs as $slug) {
                    if (isset($priorityWeights[$slug])) {
                        $score += $priorityWeights[$slug] * 0.6; // priority is a nudge, not a hard rule
                    }
                }
            }

            // Geographic relevance bonuses
            if ($citiesToVisit && $c['location_id'] && in_array((int)$c['location_id'], $citiesToVisit, true)) {
                $score += 6; // explicitly requested city/town
            }
            if ($mainRegion && $c['region'] === $mainRegion) {
                $score += 5; // near the visitor's main base
            }
            if ($startRegion && $c['region'] === $startRegion && $startRegion !== $mainRegion) {
                $score += 2; // convenient near arrival point
            }

            $c['final_score'] = round($score, 2);
        }
        unset($c);

        usort($candidates, fn($a, $b) => $b['final_score'] <=> $a['final_score']);

        // Regional diversity: soft cap of 2 picks per region before we start
        // preferring under-represented regions, so a route isn't stuck in one corner.
        $selected = [];
        $regionCount = [];
        $pool = $candidates;

        while (count($selected) < $days && !empty($pool)) {
            usort($pool, function ($a, $b) use (&$regionCount) {
                $ra = $regionCount[$a['region']] ?? 0;
                $rb = $regionCount[$b['region']] ?? 0;
                if ($ra !== $rb) return $ra <=> $rb; // fewer picks from this region first
                return $b['final_score'] <=> $a['final_score'];
            });
            $pick = array_shift($pool);
            $selected[] = $pick;
            $regionCount[$pick['region']] = ($regionCount[$pick['region']] ?? 0) + 1;
        }

        // If fewer candidates than days (narrow interests), cycle back through
        // the best-scoring candidates to fill remaining days.
        $i = 0;
        while (count($selected) < $days && !empty($candidates)) {
            $selected[] = $candidates[$i % count($candidates)];
            $i++;
        }

        if ($preferredDestinationIds && count($selected) > 0) {
            $preferredFirst = [];
            foreach ($selected as $candidate) {
                if (!empty($candidate['preferred'])) {
                    $preferredFirst[] = $candidate;
                }
            }
            foreach ($selected as $candidate) {
                if (empty($candidate['preferred'])) {
                    $preferredFirst[] = $candidate;
                }
            }
            $selected = array_slice($preferredFirst, 0, $days);
        }

        $items = [];
        foreach ($selected as $idx => $dest) {
            $items[] = [
                'day' => $idx + 1,
                'destination' => $dest,
                'score' => $dest['final_score'],
            ];
        }

        return [
            'items' => $items,
            'meta' => [
                'interests' => $interestSlugs,
                'budget_tier' => $budgetTier,
                'days' => $days,
                'travelers' => $travelers,
                'travel_style' => $travelStyle,
                'starting_location_id' => $startingLocationId,
                'main_destination_id' => $mainDestinationId,
                'cities_to_visit' => $citiesToVisit,
                'include_popular' => $includePopular,
                'include_hidden_gems' => $includeHiddenGems,
                'priority_id' => $priorityId,
                'candidate_count' => count($candidates),
            ],
        ];
    }

    /** Look up a location's region so we can compare it against destination regions */
    private function regionForLocation(int $locationId): ?string
    {
        $stmt = $this->db->prepare("SELECT region FROM locations WHERE id = ?");
        $stmt->execute([$locationId]);
        $row = $stmt->fetch();
        return $row['region'] ?? null;
    }

    /** Persist a generated plan so it can be reloaded or attached to a booking */
    public function savePlan(array $result, ?int $visitorId = null, string $planName = 'My Sri Lanka Trip'): int
    {
        $meta = $result['meta'];
        $stmt = $this->db->prepare(
            "INSERT INTO trip_plans
             (visitor_id, plan_name, starting_location_id, main_destination_id, cities_to_visit,
              duration_days, travelers, budget_tier, travel_style, interests, include_popular, include_hidden_gems, priority_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $visitorId,
            $planName,
            $meta['starting_location_id'],
            $meta['main_destination_id'],
            $meta['cities_to_visit'] ? implode(',', $meta['cities_to_visit']) : null,
            $meta['days'],
            $meta['travelers'] ?? 2,
            $meta['budget_tier'],
            $meta['travel_style'],
            implode(',', $meta['interests']),
            (int)$meta['include_popular'],
            (int)$meta['include_hidden_gems'],
            $meta['priority_id'],
        ]);
        $planId = (int)$this->db->lastInsertId();

        $itemStmt = $this->db->prepare(
            "INSERT INTO trip_plan_items (trip_plan_id, destination_id, day_number, match_score, sort_order)
             VALUES (?,?,?,?,?)"
        );
        foreach ($result['items'] as $order => $item) {
            $itemStmt->execute([
                $planId,
                $item['destination']['id'],
                $item['day'],
                $item['score'],
                $order,
            ]);
        }

        return $planId;
    }
}
