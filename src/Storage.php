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

use PDO;
use mysqli;

class Storage {
    private PDO|mysqli $connection;
    public private(set) string $driver;
    public private(set) string $prefix;

    public function __construct(PDO|mysqli $connection, string $prefix = 'em_') {
        $this->connection = $connection;
        $this->prefix = $prefix;
        
        $this->driver = match (true) {
            $connection instanceof PDO => strtolower($connection->getAttribute(PDO::ATTR_DRIVER_NAME)),
            $connection instanceof mysqli => 'mysql',
            default => throw new StorageException("Unsupported database connection type.")
        };

        $this->ensureStructure();
    }

    /**
     * Creates database tables using optimized dialect matching and handles migrations/updates
     */
    private function ensureStructure(): void {
        $historyTable = $this->prefix . 'history';
        $factsTable = $this->prefix . 'facts';

        $sql = match($this->driver) {
            'sqlite' => [
                "CREATE TABLE IF NOT EXISTS {$historyTable} (id INTEGER PRIMARY KEY AUTOINCREMENT, session_id TEXT, role TEXT, content TEXT, created_at TEXT);",
                "CREATE TABLE IF NOT EXISTS {$factsTable} (session_id TEXT, fact_key TEXT, fact_value TEXT, expires_at TEXT, PRIMARY KEY (session_id, fact_key));"
            ],
            'sqlsrv' => [
                "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='{$historyTable}' AND xtype='U') CREATE TABLE {$historyTable} (id INT IDENTITY(1,1) PRIMARY KEY, session_id VARCHAR(255), role VARCHAR(50), content NVARCHAR(MAX), created_at DATETIME);",
                "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='{$factsTable}' AND xtype='U') CREATE TABLE {$factsTable} (session_id VARCHAR(255), fact_key VARCHAR(255), fact_value NVARCHAR(MAX), expires_at VARCHAR(50), PRIMARY KEY (session_id, fact_key));"
            ],
            'pgsql' => [
                "CREATE TABLE IF NOT EXISTS {$historyTable} (id SERIAL PRIMARY KEY, session_id VARCHAR(255), role VARCHAR(50), content TEXT, created_at TIMESTAMP);",
                "CREATE TABLE IF NOT EXISTS {$factsTable} (session_id VARCHAR(255), fact_key VARCHAR(255), fact_value TEXT, expires_at VARCHAR(50), PRIMARY KEY (session_id, fact_key));"
            ],
            default => [
                "CREATE TABLE IF NOT EXISTS {$historyTable} (id INT AUTO_INCREMENT PRIMARY KEY, session_id VARCHAR(255), role VARCHAR(50), content LONGTEXT, created_at DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
                "CREATE TABLE IF NOT EXISTS {$factsTable} (session_id VARCHAR(255), fact_key VARCHAR(255), fact_value LONGTEXT, expires_at VARCHAR(50), PRIMARY KEY (session_id, fact_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
            ]
        };

        $this->executeNonQuery($sql[0]);
        $this->executeNonQuery($sql[1]);

        // Self-healing: verify the 'expires_at' column exists in case the table already existed without it
        try {
            if ($this->driver === 'sqlite') {
                $cols = $this->executeQuery("PRAGMA table_info({$factsTable})");
                $hasExpires = false;
                foreach ($cols as $c) {
                    if (($c['name'] ?? '') === 'expires_at') {
                        $hasExpires = true;
                        break;
                    }
                }
                if (!$hasExpires) {
                    $this->executeNonQuery("ALTER TABLE {$factsTable} ADD COLUMN expires_at TEXT;");
                }
            } else {
                // MySQL, PGSQL, SQL Server: Try to add column, ignore errors if it already exists
                try {
                    $this->executeNonQuery("ALTER TABLE {$factsTable} ADD COLUMN expires_at VARCHAR(50);");
                } catch (\Throwable $e) {
                    // Ignore column already exists
                }
            }
        } catch (\Throwable $e) {
            // Suppress secondary query migration failures to remain extremely robust
        }
    }

    public function executeNonQuery(string $sql, array $params = []): void {
        try {
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                throw new StorageException("Failed to prepare statement: " . ($this->connection instanceof mysqli ? $this->connection->error : ""));
            }
            if ($this->connection instanceof PDO) {
                $stmt->execute($params);
            } else {
                if (!empty($params)) {
                    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                }
                $stmt->execute();
            }
        } catch (\Throwable $e) {
            if ($e instanceof StorageException) {
                throw $e;
            }
            throw new StorageException("Database query failed: " . $e->getMessage(), 0, $e);
        }
    }

    public function executeQuery(string $sql, array $params = []): array {
        try {
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                throw new StorageException("Failed to prepare statement: " . ($this->connection instanceof mysqli ? $this->connection->error : ""));
            }
            if ($this->connection instanceof PDO) {
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                if (!empty($params)) {
                    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                }
                $stmt->execute();
                return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
            }
        } catch (\Throwable $e) {
            if ($e instanceof StorageException) {
                throw $e;
            }
            throw new StorageException("Database query failed: " . $e->getMessage(), 0, $e);
        }
    }
}
