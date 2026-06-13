<?php

namespace ElePHPant;

require_once __DIR__ . '/Exceptions.php';
require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/ChatManager.php';

use PDO;

// Helper function to print test results
function assertTest(string $name, bool $expression, string $failureDetails = ''): void {
    if ($expression) {
        echo "\033[32m[PASS]\033[0m $name\n";
    } else {
        echo "\033[31m[FAIL]\033[0m $name\n";
        if ($failureDetails) {
            echo "       Details: $failureDetails\n";
        }
        exit(1);
    }
}

echo "Starting ElePHPant test suite...\n\n";

// 1. Setup in-memory PDO connection
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $chatManager = new ChatManager($pdo, 2000, 30);
    assertTest("Initialization and table structure creation", true);
} catch (\Throwable $e) {
    assertTest("Initialization and table structure creation", false, $e->getMessage());
}

// 2. Test Exceptions - Validation
try {
    new ChatManager(new PDO('sqlite::memory:'), 400);
    assertTest("Throws ValidationException for maxTokens < 500", false, "Expected ValidationException but none was thrown.");
} catch (ValidationException $e) {
    assertTest("Throws ValidationException for maxTokens < 500", true);
} catch (\Throwable $e) {
    assertTest("Throws ValidationException for maxTokens < 500", false, "Expected ValidationException but got " . get_class($e));
}

// 3. Test Exceptions - Storage
try {
    $storage = new Storage($pdo);
    // Execute a completely broken query to trigger StorageException
    $storage->executeNonQuery("SELECT FROM non_existing_table_broken_sql;");
    assertTest("Throws StorageException for bad queries", false, "Expected StorageException but none was thrown.");
} catch (StorageException $e) {
    assertTest("Throws StorageException for bad queries", true);
} catch (\Throwable $e) {
    assertTest("Throws StorageException for bad queries", false, "Expected StorageException but got " . get_class($e));
}

// 4. Test Add message and TTL purge behavior
try {
    $session = 'test_session_ttl';
    $chatManager = new ChatManager($pdo, 1000, 5); // purge after 5 days
    
    // Simulate inserting a message from 10 days ago directly to DB
    $oldDate = (new \DateTimeImmutable())->modify('-10 days')->format('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO em_history (session_id, role, content, created_at) VALUES (?, ?, ?, ?)")
        ->execute([$session, 'user', 'Old message from 10 days ago', $oldDate]);

    // Check it's there
    $count = $pdo->query("SELECT COUNT(*) FROM em_history WHERE session_id = 'test_session_ttl'")->fetchColumn();
    assertTest("Old message successfully injected directly", $count == 1);

    // Call addMessage to log a new message (this should trigger the automatic TTL purge)
    $chatManager->addMessage($session, 'assistant', 'New response');
    
    // Verify old message was deleted and new one was added
    $messages = $pdo->prepare("SELECT content FROM em_history WHERE session_id = ? ORDER BY id ASC");
    $messages->execute([$session]);
    $results = $messages->fetchAll(PDO::FETCH_COLUMN);
    
    assertTest("TTL Purge: Old message automatically deleted", !in_array('Old message from 10 days ago', $results));
    assertTest("TTL Purge: New message successfully added", in_array('New response', $results));
} catch (\Throwable $e) {
    assertTest("TTL Purge test", false, $e->getMessage());
}

// 5. Test Facts UPSERT (saveFact)
try {
    $session = 'test_session_facts';
    $chatManager = new ChatManager($pdo, 2000, 30);
    
    // First save
    $chatManager->saveFact($session, 'location', 'Lisboa');
    $context = $chatManager->getOptimizedContext($session);
    assertTest("Save Fact: initial save works", count($context) === 1 && str_contains($context[0]['content'], '[location: Lisboa]'));
    
    // Update (ON CONFLICT DO UPDATE)
    $chatManager->saveFact($session, 'location', 'Porto');
    $context = $chatManager->getOptimizedContext($session);
    assertTest("Save Fact: UPSERT overwrites existing key", count($context) === 1 && str_contains($context[0]['content'], '[location: Porto]') && !str_contains($context[0]['content'], 'Lisboa'));
} catch (\Throwable $e) {
    assertTest("Facts UPSERT test", false, $e->getMessage());
}

// 6. Test Token Limiting (maxTokens sliding window)
try {
    $session = 'test_session_tokens';
    // Let's set maxTokens = 500 (approx. 2000 characters total allowed)
    $chatManager = new ChatManager($pdo, 500, 30);
    
    // Save a small fact first to consume some token budget
    $chatManager->saveFact($session, 'name', 'Joe'); // "[name: Joe]" is 11 characters (~3 tokens)
    
    // Add multiple long messages. 1 token ≈ 4 characters. Let's add 5 messages of 600 characters (~150 tokens each).
    // Total budget is 500 tokens. Fact consumes ~3, leaving ~497 tokens for history.
    // With 497 tokens, we can fit at most 3 messages (3 * 150 = 450 tokens). 4 messages would be 600 tokens (exceeds budget of 497).
    // So messages 5, 4, 3 should be kept, and message 2 and 1 should be truncated.
    
    $msg1 = str_repeat('A', 600); // 150 tokens
    $msg2 = str_repeat('B', 600); // 150 tokens
    $msg3 = str_repeat('C', 600); // 150 tokens
    $msg4 = str_repeat('D', 600); // 150 tokens
    $msg5 = str_repeat('E', 600); // 150 tokens
    
    $chatManager->addMessage($session, 'user', $msg1);
    $chatManager->addMessage($session, 'assistant', $msg2);
    $chatManager->addMessage($session, 'user', $msg3);
    $chatManager->addMessage($session, 'assistant', $msg4);
    $chatManager->addMessage($session, 'user', $msg5);
    
    $context = $chatManager->getOptimizedContext($session);
    
    // First item is system prompt (facts)
    assertTest("Context has system prompt at index 0", $context[0]['role'] === 'system');
    
    // Let's check which messages are included. The newest messages (E, D, C) must be there.
    $contents = array_column($context, 'content');
    
    assertTest("Sliding window includes most recent message E", in_array($msg5, $contents));
    assertTest("Sliding window includes message D", in_array($msg4, $contents));
    assertTest("Sliding window includes message C", in_array($msg3, $contents));
    assertTest("Sliding window excludes oldest message A", !in_array($msg1, $contents));
    assertTest("Sliding window excludes message B", !in_array($msg2, $contents));
    
    // Verify chronological order of the returned history (C, then D, then E)
    $historyIndices = [];
    foreach ($context as $idx => $item) {
        if ($item['role'] !== 'system') {
            $historyIndices[] = $item['content'][0]; // Get the repeat character ('C', 'D', or 'E')
        }
    }
    
    assertTest("Returned context history is in correct chronological order (C -> D -> E)", implode('', $historyIndices) === 'CDE');
} catch (\Throwable $e) {
    assertTest("Token Limiting test", false, $e->getMessage() . "\n" . $e->getTraceAsString());
}

// 7. Test Lifecycle Methods
try {
    $session = 'test_session_lifecycle';
    $chatManager = new ChatManager($pdo, 2000, 30);
    
    $chatManager->saveFact($session, 'key1', 'val1');
    $chatManager->saveFact($session, 'key2', 'val2');
    $chatManager->addMessage($session, 'user', 'message 1');
    
    // Test deleteFact
    $chatManager->deleteFact($session, 'key1');
    $context = $chatManager->getOptimizedContext($session);
    assertTest("deleteFact removes only the targeted key", !str_contains($context[0]['content'], 'key1') && str_contains($context[0]['content'], 'key2'));
    
    // Test clearFacts
    $chatManager->clearFacts($session);
    $context = $chatManager->getOptimizedContext($session);
    // First element should be message 1, and no system prompt with facts since facts were cleared
    assertTest("clearFacts removes all facts from context", count($context) === 1 && $context[0]['role'] === 'user');
    
    // Test clearHistory
    $chatManager->saveFact($session, 'key3', 'val3');
    $chatManager->clearHistory($session);
    $context = $chatManager->getOptimizedContext($session);
    assertTest("clearHistory removes all history messages", count($context) === 1 && $context[0]['role'] === 'system');
    
    // Test deleteSession
    $chatManager->addMessage($session, 'user', 'message 2');
    $chatManager->deleteSession($session);
    $context = $chatManager->getOptimizedContext($session);
    assertTest("deleteSession removes all trace of the session (empty context)", count($context) === 0);
} catch (\Throwable $e) {
    assertTest("Lifecycle methods test", false, $e->getMessage());
}

echo "\n\033[32mAll tests passed successfully!\033[0m\n";
