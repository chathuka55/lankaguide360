<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/ChatbotEngine.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

$message = trim($payload['message'] ?? '');
$sessionId = preg_replace('/[^a-zA-Z0-9_]/', '', $payload['session_id'] ?? 'anon');

if ($message === '') {
    echo json_encode(['reply' => "Could you type a message? I'm listening!", 'action' => null]);
    exit;
}

try {
    $engine = new ChatbotEngine(getDbConnection());
    $result = $engine->reply($message, $sessionId);
    echo json_encode([
        'reply'       => $result['reply'],
        'action'      => $result['action'],
        'suggestions' => $result['suggestions'] ?? [],
        'cards'       => $result['cards'] ?? [],
    ]);
} catch (Throwable $e) {
    error_log('Chatbot API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['reply' => 'Sorry, something went wrong on my end.', 'action' => null]);
}
