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

namespace ElePHPant;

require_once __DIR__ . '/Exceptions.php';
require_once __DIR__ . '/Storage.php';

use DateTimeImmutable;

class ChatManager {
    private Storage $storage;
    
    public private(set) int $maxTokens {
        set => $value < 500 ? throw new ValidationException("Token limit is too low.") : $value;
    }
    
    public private(set) int $purgeAfterDays;

    public function __construct(
        object $existingConnection,
        int $maxTokens = 2000,
        int $purgeAfterDays = 30
    ) {
        $this->storage = new Storage($existingConnection);
        $this->maxTokens = $maxTokens;
        $this->purgeAfterDays = $purgeAfterDays;
    }

    /**
     * Logs conversation turn and triggers inline fast maintenance
     */
    public function addMessage(string $sessionId, string $role, string $content): void {
        $this->storage->executeNonQuery(
            "INSERT INTO em_history (session_id, role, content, created_at) VALUES (?, ?, ?, ?)",
            [$sessionId, $role, trim($content), (new DateTimeImmutable())->format('Y-m-d H:i:s')]
        );
        
        $this->storage->executeNonQuery(
            "DELETE FROM em_history WHERE session_id = ? AND created_at < ?",
            [$sessionId, (new DateTimeImmutable())->modify("-{$this->purgeAfterDays} days")->format('Y-m-d H:i:s')]
        );
    }

    /**
     * Stores metadata using optimized UPSERT statements tailored per engine
     */
    public function saveFact(string $sessionId, string $key, string $value): void {
        $sql = match($this->storage->driver) {
            'sqlite' => "INSERT INTO em_facts (session_id, fact_key, fact_value) VALUES (?, ?, ?) ON CONFLICT(session_id, fact_key) DO UPDATE SET fact_value = ?",
            'sqlsrv' => "MERGE em_facts AS t USING (SELECT ? AS s, ? AS k) AS src ON (t.session_id = src.s AND t.fact_key = src.k) WHEN MATCHED THEN UPDATE SET fact_value = ? WHEN NOT MATCHED THEN INSERT (session_id, fact_key, fact_value) VALUES (src.s, src.k, ?);",
            'pgsql'  => "INSERT INTO em_facts (session_id, fact_key, fact_value) VALUES (?, ?, ?) ON CONFLICT (session_id, fact_key) DO UPDATE SET fact_value = EXCLUDED.fact_value",
            default  => "INSERT INTO em_facts (session_id, fact_key, fact_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE fact_value = ?"
        };

        if ($this->storage->driver === 'pgsql') {
            $this->storage->executeNonQuery($sql, [$sessionId, $key, $value]);
        } else {
            $this->storage->executeNonQuery($sql, [$sessionId, $key, $value, $value]);
        }
    }

    /**
     * Deletes a specific fact for a session
     */
    public function deleteFact(string $sessionId, string $key): void {
        $this->storage->executeNonQuery(
            "DELETE FROM em_facts WHERE session_id = ? AND fact_key = ?",
            [$sessionId, $key]
        );
    }

    /**
     * Clears all facts for a session
     */
    public function clearFacts(string $sessionId): void {
        $this->storage->executeNonQuery(
            "DELETE FROM em_facts WHERE session_id = ?",
            [$sessionId]
        );
    }

    /**
     * Clears short-term conversation history for a session
     */
    public function clearHistory(string $sessionId): void {
        $this->storage->executeNonQuery(
            "DELETE FROM em_history WHERE session_id = ?",
            [$sessionId]
        );
    }

    /**
     * Deletes all data (facts and history) for a session
     */
    public function deleteSession(string $sessionId): void {
        $this->storage->executeNonQuery(
            "DELETE FROM em_facts WHERE session_id = ?",
            [$sessionId]
        );
        $this->storage->executeNonQuery(
            "DELETE FROM em_history WHERE session_id = ?",
            [$sessionId]
        );
    }

    /**
     * Builds memory array payload utilizing pipeline processing and sliding window token limiting
     */
    public function getOptimizedContext(string $sessionId): array {
        $context = [];
        $systemPrompt = '';
        $systemTokens = 0;

        if ($facts = $this->storage->executeQuery("SELECT fact_key, fact_value FROM em_facts WHERE session_id = ?", [$sessionId])) {
            $buffer = "Known facts about this user/session: ";
            foreach ($facts as $f) {
                $buffer .= "[{$f['fact_key']}: {$f['fact_value']}] ";
            }
            $systemPrompt = rtrim($buffer);
            // Estimate tokens: 1 token ~ 4 characters
            $systemTokens = (int) ceil(mb_strlen($systemPrompt, 'UTF-8') / 4);
            $context[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        $remainingTokens = $this->maxTokens - $systemTokens;

        // Fetch history ordered by ID DESC (newest first) to apply token budget
        $history = $this->storage->executeQuery(
            "SELECT role, content FROM em_history WHERE session_id = ? ORDER BY id DESC",
            [$sessionId]
        );

        $historyMessages = [];
        $accumulatedTokens = 0;

        foreach ($history as $m) {
            $messageContent = $m['content'];
            $messageTokens = (int) ceil(mb_strlen($messageContent, 'UTF-8') / 4);

            if ($accumulatedTokens + $messageTokens > $remainingTokens) {
                break;
            }

            array_unshift($historyMessages, ['role' => $m['role'], 'content' => $messageContent]);
            $accumulatedTokens += $messageTokens;
        }

        return array_merge($context, $historyMessages);
    }
}