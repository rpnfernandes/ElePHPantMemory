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
require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/AIProvider.php';
require_once __DIR__ . '/Intelligence.php';

use DateTimeImmutable;

class ChatManager {
    private Storage $storage;
    private ?Intelligence $intelligence = null;

    public bool $autoExtractFacts = false;
    
    public private(set) int $maxTokens {
        set => $value < 500 ? throw new ValidationException("Token limit is too low.") : $value;
    }
    
    public private(set) int $purgeAfterDays;

    public function __construct(
        object $existingConnection,
        int $maxTokens = 2000,
        int $purgeAfterDays = 30,
        ?AIProvider $aiProvider = null
    ) {
        $this->storage = new Storage($existingConnection);
        $this->maxTokens = $maxTokens;
        $this->purgeAfterDays = $purgeAfterDays;
        
        if ($aiProvider !== null) {
            $this->intelligence = new Intelligence($aiProvider);
        }
    }

    /**
     * Logs conversation turn and triggers inline fast maintenance
     */
    public function addMessage(string $sessionId, string $role, string $content, bool $triggerExtraction = false): void {
        $this->storage->executeNonQuery(
            "INSERT INTO em_history (session_id, role, content, created_at) VALUES (?, ?, ?, ?)",
            [$sessionId, $role, trim($content), (new DateTimeImmutable())->format('Y-m-d H:i:s')]
        );
        
        $this->storage->executeNonQuery(
            "DELETE FROM em_history WHERE session_id = ? AND created_at < ?",
            [$sessionId, (new DateTimeImmutable())->modify("-{$this->purgeAfterDays} days")->format('Y-m-d H:i:s')]
        );

        if ($triggerExtraction || $this->autoExtractFacts) {
            $this->extractFactsFromHistory($sessionId);
        }
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
     * Retrieves a specific fact for a session
     */
    public function getFact(string $sessionId, string $key): ?string {
        $result = $this->storage->executeQuery(
            "SELECT fact_value FROM em_facts WHERE session_id = ? AND fact_key = ?",
            [$sessionId, $key]
        );
        return !empty($result) ? $result[0]['fact_value'] : null;
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
     * Extracts facts automatically from the most recent history messages and saves them.
     *
     * @param string $sessionId
     * @param int $limit Number of messages to analyze
     * @return array The newly extracted facts
     */
    public function extractFactsFromHistory(string $sessionId, int $limit = 10): array {
        if ($this->intelligence === null) {
            return [];
        }

        $history = $this->storage->executeQuery(
            "SELECT role, content FROM em_history WHERE session_id = ? ORDER BY id DESC LIMIT ?",
            [$sessionId, $limit]
        );

        if (empty($history)) {
            return [];
        }

        // Reverse to get chronological order (oldest to newest)
        $history = array_reverse($history);

        $extracted = $this->intelligence->extractFacts($history);
        foreach ($extracted as $key => $value) {
            $this->saveFact($sessionId, $key, (string)$value);
        }

        return $extracted;
    }

    /**
     * Builds memory array payload utilizing pipeline processing and sliding window token limiting.
     * If intelligence is enabled, it automatically triggers smart summarization for discarded messages.
     */
    public function getOptimizedContext(string $sessionId): array {
        $context = [];
        $systemPrompt = '';
        $systemTokens = 0;

        // 1. Gather all facts
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

        // 2. Fetch history ordered by ID DESC (newest first)
        $history = $this->storage->executeQuery(
            "SELECT id, role, content FROM em_history WHERE session_id = ? ORDER BY id DESC",
            [$sessionId]
        );

        $historyMessages = [];
        $discardMessages = [];
        $accumulatedTokens = 0;

        foreach ($history as $m) {
            $messageContent = $m['content'];
            $messageTokens = (int) ceil(mb_strlen($messageContent, 'UTF-8') / 4);

            if ($accumulatedTokens + $messageTokens > $remainingTokens) {
                $discardMessages[] = $m;
            } else {
                array_unshift($historyMessages, ['role' => $m['role'], 'content' => $messageContent]);
                $accumulatedTokens += $messageTokens;
            }
        }

        // 3. If messages were discarded and intelligence is enabled, trigger smart summarization
        if (!empty($discardMessages) && $this->intelligence !== null) {
            // Discarded messages are in reverse order (newest first), reverse them back to chronological
            $discardMessages = array_reverse($discardMessages);
            
            $existingSummary = $this->getFact($sessionId, '_conversation_summary');
            
            // Generate the new consolidated summary
            $newSummary = $this->intelligence->summarizeHistory($discardMessages, $existingSummary);
            
            if (!empty($newSummary)) {
                $this->saveFact($sessionId, '_conversation_summary', $newSummary);
                
                // Delete summarized messages from database history
                $maxDiscardId = max(array_column($discardMessages, 'id'));
                $this->storage->executeNonQuery(
                    "DELETE FROM em_history WHERE session_id = ? AND id <= ?",
                    [$sessionId, $maxDiscardId]
                );
                
                // Recalculate context with the new summary and purged history
                return $this->getOptimizedContext($sessionId);
            }
        }

        return array_merge($context, $historyMessages);
    }
}