<?php

declare(strict_types=1);

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
