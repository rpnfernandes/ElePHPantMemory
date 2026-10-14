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
