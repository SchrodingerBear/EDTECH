<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
/**
 * Innovatech PH — AI chat with OpenRouter support
 * Supports both template-based and OpenRouter AI generation
 */
header('Content-Type: application/json; charset=utf-8');

$msg = trim((string) ($_POST['message'] ?? ''));
$useOpenRouter = ($_POST['use_openrouter'] ?? 'false') === 'true';

if ($msg === '') {
  echo json_encode(['reply' => 'Type a message first — try describing a campus space, e.g. "Main Library".']);
  exit;
}

// Try OpenRouter if requested
if ($useOpenRouter) {
    $apiKey = env('OPENROUTER_TEXT_KEY', env('OPENROUTER_API_KEY', ''));
    
    if ($apiKey !== '') {
        try {
            $payload = [
                'model' => 'meta-llama/llama-3.2-3b-instruct',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You generate short, factual descriptions for educational campus locations. Do not invent information. Use only the details provided by the user. Return only the description. Write 1-2 sentences in a natural, professional tone suitable for a virtual campus tour.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $msg
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 100
            ];
            
            $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: ' . (env('APP_URL', 'http://localhost') ?: 'http://localhost'),
                'X-Title: Innovatech PH Campus AI'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            if (!$error) {
                $result = json_decode($response, true);
                if (isset($result['choices'][0]['message']['content'])) {
                    echo json_encode(['reply' => $result['choices'][0]['message']['content']]);
                    exit;
                }
            }
        } catch (Throwable $e) {
            // Fall back to template system if OpenRouter fails
            error_log('OpenRouter AI generation failed: ' . $e->getMessage());
        }
    }
}

// Fallback to template-based generation
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
    $reply = "I'm the AI-assisted description generator for your campus. I turn place names — plus available details like floor, type, or purpose — into short, tour-ready descriptions. " . ($useOpenRouter ? "I can use OpenRouter AI for natural language generation when configured." : "I run on your server with template-based generation for instant results.") . " Just name a place (e.g. \"Main Building\" or \"Physics Wing\") and I'll draft one.";
    echo json_encode(['reply' => $reply]);
    exit;
}

if (count($words) === 0) {
  if ($isGreeting) {
    $reply = "Hello! I generate concise, natural campus descriptions from the details available for buildings, rooms, areas, facilities, and 360° tour stops. " . ($useOpenRouter ? "I can use OpenRouter AI for more natural language when your API key is configured." : "I use template-based generation for instant results.") . " Just type a place name (e.g. \"Main Library\") and I'll draft one for you.";
  } else {
    $reply = "I draft 2–3 sentence descriptions for campus places. Give me a place name with at least two words (e.g. \"Science Building\") or add details like location and facilities for a richer result.";
  }
  echo json_encode(['reply' => $reply]);
  exit;
}

$openers = [
    "Here's a quick draft for \"{$topic}\":",
    "Instant reply " . ($useOpenRouter ? "from OpenRouter AI — " : "from the on-server engine — "),
    "The campus AI generated this for \"{$topic}\":",
    "Quick response: ",
];
$reply = $openers[array_rand($openers)] . "\n\n" . ai_generate_description($topic, $instName);

// If OpenRouter was requested but we fell back to templates, add a note
if ($useOpenRouter) {
    $reply .= "\n\n⚠️ Note: OpenRouter AI unavailable. Using template-based generation instead.";
}

echo json_encode(['reply' => $reply]);
