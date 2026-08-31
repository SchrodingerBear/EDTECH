<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
/**
 * Innovatech PH — instant on-server AI chat.
 * Same fast template-based engine as ai-demo.php / AI Tools: no downloads,
 * no WebGPU, no API keys — replies within a split second.
 */
header('Content-Type: application/json; charset=utf-8');

$msg = trim((string) ($_POST['message'] ?? ''));
if ($msg === '') {
    echo json_encode(['reply' => 'Type a message first — try describing a campus space, e.g. "Main Library".']);
    exit;
}

$inst = resolve_active_institution();
$instName = $inst['name'] ?? APP_NAME;

$clean = trim(preg_replace('/[^A-Za-z0-9\s.,\'-]/u', ' ', $msg));
$clean = preg_replace('/\s+/', ' ', $clean);

$quiet = ['describe', 'write', 'generate', 'make', 'create', 'tell', 'give', 'about', 'please', 'a', 'an', 'the', 'me', 'can', 'you', 'could', 'would', 'what', 'how', 'where', 'is', 'for', 'show', 'draft', 'hi', 'hello', 'hey', 'yes', 'no', 'ok', 'okay', 'thanks', 'thank', 'sure', 'yeah', 'nope', 'maybe'];
$words = array_values(array_filter(preg_split('/\s+/', strtolower($clean)), fn($w) => !in_array($w, $quiet, true) && $w !== ''));
$topic = ucwords(trim(implode(' ', array_slice($words, 0, 4))));

$isGreeting = preg_match('/^(hi|hello|hey)\b/i', $clean) === 1;
$isQuestion = $isGreeting === false && preg_match('/(\?|what\b|who\b|why\b|how\b|when\b|where\b|which\b|could you\b|can you\b|is it\b|does this\b)/i', $clean) === 1;

if ($isQuestion) {
    $reply = "I'm the built-in AI-assisted description generator for your campus. I turn place names — plus available details like floor, type, or purpose — into short, tour-ready descriptions. No internet or API needed; I run right on your server. Just name a place (e.g. \"Main Building\" or \"Physics Wing\") and I'll draft one.";
    echo json_encode(['reply' => $reply]);
    exit;
}

if (count($words) === 0) {
    if ($isGreeting) {
        $reply = "Hello! I generate concise, natural campus descriptions from the details available for buildings, rooms, areas, facilities, and 360° tour stops. Just type a place name (e.g. \"Main Library\") and I'll draft one for you.";
    } else {
        $reply = "I draft 2–3 sentence descriptions for campus places. Give me a place name with at least two words (e.g. \"Science Building\") or add details like location and facilities for a richer result.";
    }
    echo json_encode(['reply' => $reply]);
    exit;
}

$openers = [
    "Here's a quick on-board draft for \"{$topic}\":",
    "Runs instantly on your server — no downloads needed:",
    "My built-in campus AI came up with this for \"{$topic}\":",
    "Instant reply from the on-server engine —",
];
$reply = $openers[array_rand($openers)] . "\n\n" . ai_generate_description($topic, $instName);

echo json_encode(['reply' => $reply]);