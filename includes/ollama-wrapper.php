<?php
/**
 * AI API wrapper for text generation
 * Supports both local Ollama and OpenRouter
 */

class AITextWrapper {
    private $type = 'openrouter'; // 'ollama' or 'openrouter'
    private $baseUrl = 'https://openrouter.ai/api/v1/chat/completions';
    private $model = 'meta-llama/llama-3.2-3b-instruct';
    private $apiKey = '';
    
    public function __construct(string $type = 'openrouter', string $model = '', string $baseUrl = '') {
        $this->type = $type;
        
        if ($type === 'ollama') {
            $this->baseUrl = $baseUrl ?: 'http://localhost:11434/api/generate';
            $this->model = $model ?: 'llama3.2:1b';
        } else {
            $this->baseUrl = 'https://openrouter.ai/api/v1/chat/completions';
            $this->model = $model ?: 'meta-llama/llama-3.2-3b-instruct';
            $this->apiKey = env('OPENROUTER_TEXT_KEY', env('OPENROUTER_API_KEY', ''));
        }
    }
    
    public function generate(string $prompt, array $options = []): string {
        if ($this->type === 'ollama') {
            return $this->generateOllama($prompt, $options);
        } else {
            return $this->generateOpenRouter($prompt, $options);
        }
    }
    
    private function generateOllama(string $prompt, array $options = []): string {
        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => array_merge([
                'temperature' => 0.7,
                'top_p' => 0.9,
                'num_predict' => 256,
            ], $options)
        ];
        
        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new RuntimeException('Ollama connection failed: ' . $error);
        }
        
        $result = json_decode($response, true);
        if (!isset($result['response'])) {
            throw new RuntimeException('Invalid Ollama response');
        }
        
        return trim($result['response']);
    }
    
    private function generateOpenRouter(string $prompt, array $options = []): string {
        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenRouter API key not configured');
        }
        
        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You generate concise, factual descriptions for campus locations. Use only the provided details. Do not invent information. Keep it simple and direct. Write 1-2 sentences maximum. Avoid flowery or dramatic language. Be factual: Computer Lab has computers. Library has books. Main Gate is an entrance.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.2,
            'max_tokens' => 80
        ];
        
        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: ' . (env('APP_URL', 'http://localhost') ?: 'http://localhost'),
            'X-Title: Innovatech PH Campus AI'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new RuntimeException('OpenRouter connection failed: ' . $error);
        }
        
        $result = json_decode($response, true);
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new RuntimeException('Invalid OpenRouter response');
        }
        
        return trim($result['choices'][0]['message']['content']);
    }
    
    public function isAvailable(): bool {
        try {
            if ($this->type === 'ollama') {
                $ch = curl_init('http://localhost:11434/api/tags');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return $httpCode === 200;
            } else {
                return !empty($this->apiKey);
            }
        } catch (Throwable $e) {
            return false;
        }
    }
    
    public function generateCampusDescription(string $name, string $institution = '', array $details = []): string {
        // Build a structured prompt for better AI generation
        $prompt = "Generate a description for: {$name}";
        
        if ($institution !== '') {
            $prompt .= " at {$institution}";
        }
        
        if (!empty($details)) {
            $detailsList = [];
            if (!empty($details['type'])) {
                $detailsList[] = "Type: {$details['type']}";
            }
            if (!empty($details['location'])) {
                $detailsList[] = "Location: {$details['location']}";
            }
            if (!empty($details['facilities'])) {
                $detailsList[] = "Facilities: {$details['facilities']}";
            }
            if (!empty($details['purpose'])) {
                $detailsList[] = "Purpose: {$details['purpose']}";
            }
            if (!empty($details['details'])) {
                $detailsList[] = "Details: {$details['details']}";
            }
            
            if (!empty($detailsList)) {
                $prompt .= " (" . implode(', ', $detailsList) . ")";
            }
        }
        
        $prompt .= ". Write 1-2 sentences. Be factual and professional.";
        
        return $this->generate($prompt);
    }
}

// Global instance for easy access
function ai_text(): ?AITextWrapper {
    static $instance = null;
    if ($instance === null) {
        try {
            // Try OpenRouter first
            $instance = new AITextWrapper('openrouter');
            if (!$instance->isAvailable()) {
                // Fallback to Ollama if configured
                $instance = new AITextWrapper('ollama');
                if (!$instance->isAvailable()) {
                    $instance = null;
                }
            }
        } catch (Throwable $e) {
            $instance = null;
        }
    }
    return $instance;
}