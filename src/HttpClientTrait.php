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
