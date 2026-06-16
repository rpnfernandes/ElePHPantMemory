<?php

/**
 * ElePHPant Memory - Local memory and context manager for AI chatbots.
 *
 * @package   ElePHPant\Memory
 * @author    Rui Fernandes
 * @copyright 2026 Rui Fernandes
 * @license   GPL-3.0
 * @version   1.0
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
 * Native cURL client implementation for OpenAI Chat Completion API.
 */
class OpenAIProvider implements AIProvider {
    private string $apiKey;
    private string $model;
    private string $endpoint;

    public function __construct(
        string $apiKey,
        string $model = 'gpt-4o-mini',
        string $endpoint = 'https://api.openai.com/v1/chat/completions'
    ) {
        if (empty($apiKey)) {
            throw new ValidationException("OpenAI API key cannot be empty.");
        }
        $this->apiKey = $apiKey;
        $this->model = $model;
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

        $responseStr = $this->sendRequest($this->endpoint, $headers, $body);
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

    private function sendRequest(string $url, array $headers, array $body): string {
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
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new AIException("cURL Request to OpenAI failed: " . $error);
        }

        if ($httpCode >= 400) {
            throw new AIException("OpenAI API returned HTTP code {$httpCode}: " . $response);
        }

        return $response;
    }
}

/**
 * Native cURL client implementation for Gemini Generate Content API.
 */
class GeminiProvider implements AIProvider {
    private string $apiKey;
    private string $model;
    private string $endpointTemplate;

    public function __construct(
        string $apiKey,
        string $model = 'gemini-1.5-flash',
        string $endpointTemplate = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s'
    ) {
        if (empty($apiKey)) {
            throw new ValidationException("Gemini API key cannot be empty.");
        }
        $this->apiKey = $apiKey;
        $this->model = $model;
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

        $responseStr = $this->sendRequest($url, $headers, $body);
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

    private function sendRequest(string $url, array $headers, array $body): string {
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
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new AIException("cURL Request to Gemini failed: " . $error);
        }

        if ($httpCode >= 400) {
            throw new AIException("Gemini API returned HTTP code {$httpCode}: " . $response);
        }

        return $response;
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
