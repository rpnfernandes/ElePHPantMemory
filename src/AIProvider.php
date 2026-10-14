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
