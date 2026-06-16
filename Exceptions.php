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

use Exception;

/**
 * Base exception for the ElePHPant package.
 */
class ElePHPantException extends Exception {}

/**
 * Thrown when database operations or configuration fail.
 */
class StorageException extends ElePHPantException {}

/**
 * Thrown when parameter validation fails (e.g. token limits).
 */
class ValidationException extends ElePHPantException {}

/**
 * Thrown when AI API operations fail.
 */
class AIException extends ElePHPantException {}
