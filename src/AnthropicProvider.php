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
