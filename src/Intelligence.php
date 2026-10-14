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

class Intelligence {
    private AIProvider $ai;

    public function __construct(AIProvider $aiProvider) {
        $this->ai = $aiProvider;
    }

    /**
     * Analyzes conversation history to extract key facts about the user/session.
     *
     * @param array $messages Array of messages
     * @param string|null $guidelines Optional custom guidelines for extraction
     * @return array Key-value pairs of extracted facts
     * @throws AIException
     */
    public function extractFacts(array $messages, ?string $guidelines = null): array {
        if (empty($messages)) {
            return [];
        }

        $systemPrompt = "You are a precise, automated information-extraction assistant.\n"
            . "Analyze the conversation history provided below. Extract any permanent, key, or long-term facts about the user, their preferences, contact details, or business context (e.g. name, location, language preference, VAT/NIF number, order IDs, interest in products).\n\n";

        if (!empty($guidelines)) {
            $systemPrompt .= "Custom Extraction Guidelines:\n" . trim($guidelines) . "\n\n";
        }

        $systemPrompt .= "Rules:\n"
            . "1. Focus ONLY on permanent or high-value long-term facts.\n"
            . "2. Return a clean, valid JSON object where keys represent the fact labels (short, snake_case, lowercase) and values represent the fact values.\n"
            . "3. If no new long-term facts are found, or if the messages do not contain extractable facts, return an empty JSON object: {}.\n"
            . "4. Do NOT output any conversational text, markdown formatting, explanations, or backticks. Output raw JSON only.";

        $apiMessages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        // Format and append conversation messages
        foreach ($messages as $msg) {
            $apiMessages[] = [
                'role' => $msg['role'] ?? 'user',
                'content' => $msg['content'] ?? ''
            ];
        }

        try {
            $response = $this->ai->generateResponse($apiMessages, ['json' => true, 'temperature' => 0.0]);
            return $this->parseJsonArray($response);
        } catch (\Throwable $e) {
            throw new AIException("Fact extraction failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Summarizes conversation history.
     *
     * @param array $messages Array of messages to summarize
     * @param string|null $previousSummary Previous summary to consolidate with
     * @return string The summarized text
     * @throws AIException
     */
    public function summarizeHistory(array $messages, ?string $previousSummary = null): string {
        if (empty($messages)) {
            return $previousSummary ?? '';
        }

        $systemPrompt = "You are a precise conversation summarization assistant.\n"
            . "Create a concise, structured summary of the conversation history provided.\n"
            . "Focus on what was discussed, what decisions were made, user preferences, and unresolved questions.\n"
            . "Keep the summary brief and under 150 words.\n"
            . "Output ONLY the summary text. Do not include introductory phrases, explanations, or metadata.";

        $apiMessages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        if (!empty($previousSummary)) {
            $apiMessages[] = [
                'role' => 'system',
                'content' => "Summary of earlier conversation: " . $previousSummary
            ];
        }

        foreach ($messages as $msg) {
            $apiMessages[] = [
                'role' => $msg['role'] ?? 'user',
                'content' => $msg['content'] ?? ''
            ];
        }

        try {
            return trim($this->ai->generateResponse($apiMessages, ['temperature' => 0.2]));
        } catch (\Throwable $e) {
            throw new AIException("History summarization failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Safely parses JSON response array, stripping potential markdown wrapper backticks.
     */
    private function parseJsonArray(string $response): array {
        $text = trim($response);
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s+|\s+```$/', '', $text);
            $text = trim($text);
        }

        if (empty($text)) {
            return [];
        }

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Fallback: search for first '{' and last '}'
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $jsonCandidate = substr($text, $start, $end - $start + 1);
            $decodedCandidate = json_decode($jsonCandidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedCandidate)) {
                return $decodedCandidate;
            }
        }

        return [];
    }
}
