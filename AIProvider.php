<?php

/**
 * ElePHPant Memory - Local memory and context manager for AI chatbots.
 *
 * @package   ElePHPant\Memory
 * @author    Rui Fernandes
 * @copyright 2026 Rui Fernandes
 * @license   GPL-3.0
 * @version   2.0.0
 */

namespace ElePHPantMemory;

require_once __DIR__ . '/Exceptions.php';

interface AIProvider {
    /**
     * Sends conversation messages to the LLM and returns the text response.
     *
     * @param array $messages Array of messages, each formatted as ['role' => '...', 'content' => '...']
     * @param array $options Optional settings (e.g. ['json' => true])
     * @return string The raw text response from the model
     * @throws AIException if the request fails
     */
    public function generateResponse(array $messages, array $options = []): string;
}

/**
 * Helper trait containing native cURL HTTP request execution code to avoid duplication.
 */
trait HttpClientTrait {
    /**
     * Executes a POST request using native PHP cURL.
     *
     * @param string $url
     * @param array $headers
     * @param array $body
     * @param int $timeout
     * @return string
     * @throws AIException
     */
    private function sendRequest(string $url, array $headers, array $body, int $timeout): string {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new AIException("Failed to initialize cURL.");
        }

        $jsonPayload = json_encode($body);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new AIException("cURL Request failed: " . $error);
        }

        if ($httpCode >= 400) {
            throw new AIException("API returned HTTP code {$httpCode}: " . $response);
        }

        return $response;
    }
}

/**
 * Native cURL client implementation for OpenAI Chat Completion API.
 */
class OpenAIProvider implements AIProvider {
    use HttpClientTrait;

    private string $apiKey;
    public private(set) string $model;
    public private(set) int $timeout;
    private string $endpoint;

    public function __construct(
        string $apiKey,
        string $model = 'gpt-4o-mini',
        int $timeout = 30,
        string $endpoint = 'https://api.openai.com/v1/chat/completions'
    ) {
        if (empty($apiKey)) {
            throw new ValidationException("OpenAI API key cannot be empty.");
        }
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->timeout = $timeout;
        $this->endpoint = $endpoint;
    }

    public function generateResponse(array $messages, array $options = []): string {
        $body = [
            'model' => $this->model,
            'messages' => $messages,
        ];

        if ($options['json'] ?? false) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        if (isset($options['temperature'])) {
            $body['temperature'] = $options['temperature'];
        }

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        $timeout = $options['timeout'] ?? $this->timeout;
        $responseStr = $this->sendRequest($this->endpoint, $headers, $body, $timeout);
        $data = json_decode($responseStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AIException("Failed to decode OpenAI JSON response: " . json_last_error_msg());
        }

        if (isset($data['error'])) {
            throw new AIException("OpenAI API Error: " . ($data['error']['message'] ?? 'Unknown error'));
        }

        $content = $data['choices'][0]['message']['content'] ?? null;
        if ($content === null) {
            throw new AIException("OpenAI response did not contain message content.");
        }

        return $content;
    }
}

/**
 * Native cURL client implementation for Gemini Generate Content API.
 */
class GeminiProvider implements AIProvider {
    use HttpClientTrait;

    private string $apiKey;
    public private(set) string $model;
    public private(set) int $timeout;
    private string $endpointTemplate;

    public function __construct(
        string $apiKey,
        string $model = 'gemini-1.5-flash',
        int $timeout = 30,
        string $endpointTemplate = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s'
    ) {
        if (empty($apiKey)) {
            throw new ValidationException("Gemini API key cannot be empty.");
        }
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->timeout = $timeout;
        $this->endpointTemplate = $endpointTemplate;
    }

    public function generateResponse(array $messages, array $options = []): string {
        $url = sprintf($this->endpointTemplate, $this->model, $this->apiKey);

        $systemInstructionText = '';
        $contents = [];

        foreach ($messages as $msg) {
            $role = strtolower($msg['role'] ?? 'user');
            $content = $msg['content'] ?? '';

            if ($role === 'system') {
                $systemInstructionText .= (empty($systemInstructionText) ? '' : "\n") . $content;
            } else {
                // Map assistant to model
                $geminiRole = ($role === 'assistant') ? 'model' : 'user';
                $contents[] = [
                    'role' => $geminiRole,
                    'parts' => [['text' => $content]],
                ];
            }
        }

        $body = [
            'contents' => $contents,
        ];

        if (!empty($systemInstructionText)) {
            $body['systemInstruction'] = [
                'parts' => [['text' => $systemInstructionText]]
            ];
        }

        $generationConfig = [];
        if ($options['json'] ?? false) {
            $generationConfig['responseMimeType'] = 'application/json';
        }
        if (isset($options['temperature'])) {
            $generationConfig['temperature'] = $options['temperature'];
        }

        if (!empty($generationConfig)) {
            $body['generationConfig'] = $generationConfig;
        }

        $headers = [
            'Content-Type: application/json',
        ];

        $timeout = $options['timeout'] ?? $this->timeout;
        $responseStr = $this->sendRequest($url, $headers, $body, $timeout);
        $data = json_decode($responseStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AIException("Failed to decode Gemini JSON response: " . json_last_error_msg());
        }

        if (isset($data['error'])) {
            throw new AIException("Gemini API Error: " . ($data['error']['message'] ?? 'Unknown error'));
        }

        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($content === null) {
            throw new AIException("Gemini response did not contain generate content text.");
        }

        return $content;
    }
}

/**
 * Native cURL client implementation for Anthropic (Claude) Messages API.
 */
class AnthropicProvider implements AIProvider {
    use HttpClientTrait;

    private string $apiKey;
    public private(set) string $model;
    public private(set) int $timeout;
    private string $endpoint;

    public function __construct(
        string $apiKey,
        string $model = 'claude-3-5-sonnet-latest',
        int $timeout = 30,
        string $endpoint = 'https://api.anthropic.com/v1/messages'
    ) {
        if (empty($apiKey)) {
            throw new ValidationException("Anthropic API key cannot be empty.");
        }
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->timeout = $timeout;
        $this->endpoint = $endpoint;
    }

    public function generateResponse(array $messages, array $options = []): string {
        $systemInstructions = '';
        $filteredMessages = [];

        foreach ($messages as $msg) {
            $role = strtolower($msg['role'] ?? 'user');
            $content = $msg['content'] ?? '';

            if ($role === 'system') {
                $systemInstructions .= (empty($systemInstructions) ? '' : "\n") . $content;
            } else {
                $filteredMessages[] = [
                    'role' => $role,
                    'content' => $content,
                ];
            }
        }

        $body = [
            'model' => $this->model,
            'messages' => $filteredMessages,
            'max_tokens' => $options['max_tokens'] ?? 1024,
        ];

        if (!empty($systemInstructions)) {
            $body['system'] = $systemInstructions;
        }

        if (isset($options['temperature'])) {
            $body['temperature'] = $options['temperature'];
        }

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01',
        ];

        $timeout = $options['timeout'] ?? $this->timeout;
        $responseStr = $this->sendRequest($this->endpoint, $headers, $body, $timeout);
        $data = json_decode($responseStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AIException("Failed to decode Anthropic JSON response: " . json_last_error_msg());
        }

        if (isset($data['error'])) {
            throw new AIException("Anthropic API Error: " . ($data['error']['message'] ?? 'Unknown error'));
        }

        $content = $data['content'][0]['text'] ?? null;
        if ($content === null) {
            throw new AIException("Anthropic response did not contain message text.");
        }

        return $content;
    }
}

/**
 * Custom AIProvider that delegates execution to a user-provided callback.
 * Very useful for mocking/testing, or when integrating with framework-specific LLM wrappers.
 */
class CustomProvider implements AIProvider {
    /** @var callable */
    private $callback;

    public function __construct(callable $callback) {
        $this->callback = $callback;
    }

    public function generateResponse(array $messages, array $options = []): string {
        return ($this->callback)($messages, $options);
    }
}
