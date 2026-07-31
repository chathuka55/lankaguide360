<?php
/**
 * ChatbotEngine — keyword/rule-based intent matcher for the LankaGuide 360
 * tourism assistant ("Bot360"). Intents and their trigger keywords live in
 * the chatbot_intents table so non-technical admins can extend the bot's
 * knowledge without touching code.
 *
 * Tuning in this version:
 *  • Typo-tolerant, token-level matching (Levenshtein) on top of phrase matching.
 *  • Lightweight synonym expansion so casual wording still matches.
 *  • Contextual quick-reply "suggestions" returned per intent.
 *  • Rich destination "cards" (with photos) for discovery intents.
 */
class ChatbotEngine
{
    private PDO $db;

    /** Contextual follow-up chips shown after each answer. */
    private const SUGGESTIONS = [
        'greeting'      => ['Plan a trip', 'Best beaches', 'Wildlife safari', 'Best time to visit'],
        'trip_planning' => ['Best time to visit', 'Wildlife safari', 'Hill country tea', 'Budget for 5 days'],
        'beaches'       => ['Whale watching', 'Surfing Arugam Bay', 'Plan a beach trip'],
        'wildlife'      => ['Yala or Udawalawe?', 'Best time for safari', 'Plan a wildlife trip'],
        'culture'       => ['Climb Sigiriya', 'Kandy temple', 'Plan a cultural trip'],
        'hill_country'  => ['Ella by train', 'Tea factory tour', 'Plan a hill-country trip'],
        'budget'        => ['Best value hotels', 'Cheap eats', 'Plan a budget trip'],
        'best_time'     => ['South coast season', 'East coast season', 'Plan a trip'],
        'vehicle'       => ['Van with driver', 'Self-drive options', 'Plan a trip'],
        'guide'         => ['English guides', 'Regional guides', 'Plan a trip'],
        'booking'       => ['How payment works', 'Track my booking', 'Plan a trip'],
        'thanks'        => ['Plan a trip', 'Browse destinations'],
        'fallback'      => ['Plan a trip', 'Best beaches', 'Wildlife safari', 'Best time to visit'],
    ];

    /** Intents that should also show destination cards, mapped to a category slug. */
    private const CARD_CATEGORY = [
        'beaches'      => 'beaches',
        'wildlife'     => 'wildlife',
        'culture'      => 'culture',
        'hill_country' => 'hill-country',
    ];

    /** Cheap synonym expansion applied to the incoming message. */
    private const SYNONYMS = [
        'kids' => 'family', 'children' => 'family', 'child' => 'family',
        'hike' => 'hiking', 'hikes' => 'hiking', 'trek' => 'hiking', 'trekking' => 'hiking',
        'cheapest' => 'cheap budget', 'affordable' => 'cheap budget', 'money' => 'budget',
        'animals' => 'wildlife', 'leopards' => 'leopard wildlife', 'elephants' => 'elephant wildlife',
        'sea' => 'beach', 'ocean' => 'beach', 'seaside' => 'beach',
        'mountains' => 'hill country', 'mountain' => 'hill country', 'hills' => 'hill country',
        'car' => 'vehicle', 'van' => 'vehicle', 'transport' => 'vehicle',
        'weather' => 'best time season', 'rain' => 'best time season', 'monsoon' => 'best time season',
        'itinerary' => 'plan a trip', 'plan' => 'plan a trip',
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function reply(string $message, string $sessionId): array
    {
        $normalized = $this->expand(strtolower(trim($message)));
        $tokens = $this->tokens($normalized);

        $intents = $this->db->query("SELECT * FROM chatbot_intents")->fetchAll();

        $best = null;
        $bestScore = 0;

        foreach ($intents as $intent) {
            if ($intent['intent_key'] === 'fallback') continue;
            $score = $this->scoreIntent($intent['keywords'], $normalized, $tokens);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $intent;
            }
        }

        // Require a minimum confidence, else fall back gracefully.
        if (!$best || $bestScore < 3) {
            $stmt = $this->db->prepare("SELECT * FROM chatbot_intents WHERE intent_key='fallback' LIMIT 1");
            $stmt->execute();
            $best = $stmt->fetch();
        }

        $reply = $best['response'] ?? "I'm still learning about that — try asking about destinations, budgets or trip planning.";
        $action = $best['follow_up_action'] ?? null;
        $matchedKey = $best['intent_key'] ?? 'fallback';

        $suggestions = self::SUGGESTIONS[$matchedKey] ?? self::SUGGESTIONS['fallback'];
        $cards = isset(self::CARD_CATEGORY[$matchedKey])
            ? $this->cards(self::CARD_CATEGORY[$matchedKey])
            : [];

        $this->log($sessionId, $message, $matchedKey, $reply);

        return [
            'reply'       => $reply,
            'action'      => $action,
            'intent'      => $matchedKey,
            'suggestions' => $suggestions,
            'cards'       => $cards,
        ];
    }

    /** Expand casual synonyms into canonical keywords. */
    private function expand(string $text): string
    {
        $words = preg_split('/\s+/', $text);
        $out = [];
        foreach ($words as $w) {
            $clean = preg_replace('/[^a-z0-9]/', '', $w);
            $out[] = $w;
            if ($clean !== '' && isset(self::SYNONYMS[$clean])) {
                $out[] = self::SYNONYMS[$clean];
            }
        }
        return implode(' ', $out);
    }

    private function tokens(string $text): array
    {
        $parts = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($parts, fn($t) => strlen($t) >= 3));
    }

    /**
     * Score how well an intent's comma-separated keywords match the message.
     * Exact phrase hits score highest; single-word hits and near (typo) hits
     * add partial credit so casual, misspelled input still works.
     */
    private function scoreIntent(string $keywordCsv, string $normalized, array $tokens): int
    {
        $keywords = array_filter(array_map('trim', explode(',', strtolower($keywordCsv))));
        $score = 0;

        foreach ($keywords as $kw) {
            if ($kw === '') continue;

            // Whole phrase present → strong, length-weighted signal.
            if (str_contains($normalized, $kw)) {
                $score += strlen($kw);
                continue;
            }

            // Otherwise, token-level matching with typo tolerance.
            $kwTokens = preg_split('/\s+/', $kw);
            foreach ($kwTokens as $kt) {
                if (strlen($kt) < 3) continue;
                foreach ($tokens as $t) {
                    if ($t === $kt) { $score += 3; break; }
                    if (strlen($kt) >= 4 && levenshtein($t, $kt) <= 1) { $score += 2; break; }
                }
            }
        }

        return $score;
    }

    /** Top-rated destinations for a category, with resolved photo URLs. */
    private function cards(string $categorySlug): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT d.name, d.slug, d.region, d.rating, d.short_description, d.image_url
             FROM destinations d
             JOIN destination_categories dc ON dc.destination_id = d.id
             JOIN categories c ON c.id = dc.category_id
             WHERE c.slug = ? AND d.is_active = 1
             ORDER BY d.rating DESC LIMIT 3"
        );
        $stmt->execute([$categorySlug]);
        $rows = $stmt->fetchAll();

        $cards = [];
        foreach ($rows as $r) {
            $cards[] = [
                'name'   => $r['name'],
                'region' => $r['region'],
                'rating' => number_format((float)$r['rating'], 1),
                'desc'   => $r['short_description'],
                'link'   => url('destination-details.php?slug=' . urlencode($r['slug'])),
                'image'  => img_src($r['image_url']) ?: null,
            ];
        }
        return $cards;
    }

    private function log(string $sessionId, string $message, string $intent, string $reply): void
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO chatbot_logs (session_id, user_message, matched_intent, bot_response) VALUES (?,?,?,?)"
            );
            $stmt->execute([$sessionId, $message, $intent, $reply]);
        } catch (Throwable $e) {
            error_log('Chatbot log failed: ' . $e->getMessage());
        }
    }
}
