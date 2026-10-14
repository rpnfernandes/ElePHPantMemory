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
