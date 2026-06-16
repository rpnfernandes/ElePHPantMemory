# ElePHPantMemory 🐘💬

[![Version](https://img.shields.io/badge/version-1.1.0-green.svg)](#)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-8892BF.svg)](https://www.php.net)

An ultra-lightweight, zero-dependency local memory and context manager for AI chatbots. Written in pure, modern PHP 8.4+.

*Gestor de memória e contexto local para chatbots de IA. Ultra leve, rápido e sem dependências externas. Escrito em PHP 8.4+ puro e moderno.*

---

## 💡 The Problem / O Problema

### English
LLM APIs (OpenAI, Claude, Gemini) are **stateless** and **expensive**. To maintain a conversation, developers usually send the entire chat history back and forth. As conversations grow, token consumption skyrockets, API bills explode, and the AI starts to lose track of the core topic.

### Português (Portugal)
As APIs de LLMs (OpenAI, Claude, Gemini) são **stateless** (não guardam estado) e **caras**. Para manter a conversa, os programadores tendem a enviar todo o histórico de mensagens a cada novo pedido. Com o crescimento da conversa, o consumo de tokens dispara, a fatura da API explode e a IA começa a perder o fio à meada.

---

## 🚀 The Solution: How ElePHPantMemory Works / A Solução

### English
ElePHPantMemory sits between your application and the AI API. It manages chat context using a two-tier architecture running entirely inside your host project's existing database:
1. **The Short-Term Memory:** Keeps only the most recent messages to maintain natural conversation flow.
2. **The Long-Term Fact Sheet:** Extracts and preserves permanent business facts (e.g., user preferences, order IDs, VAT/NIF numbers) in a dedicated local table, injecting them as system prompts.
3. **The Intelligence Layer:** Automatically extracts facts from dialogue and summarizes older history when the token limit is reached, maintaining continuity without context loss.

### Português (Portugal)
O ElePHPantMemory funciona como um filtro inteligente entre a tua aplicação e a API de IA. Ele gere o contexto usando uma arquitetura de duas camadas que corre diretamente na base de dados já existente do teu projeto:
1. **Memória de Curto Prazo:** Mantém apenas as mensagens mais recentes para garantir a fluidez natural do diálogo.
2. **Ficha de Factos de Longo Prazo:** Extrai e preserva factos permanentes do negócio ou utilizador (ex: preferências, NIF, IDs de encomendas) numa tabela local, injetando-os como prompts de sistema.
3. **Camada de Inteligência:** Extrai automaticamente factos da conversa e sumariza o histórico antigo quando o limite de tokens é atingido, mantendo o contexto sem estourar o orçamento.

---

## 📊 Features & Comparison / Caraterísticas e Comparação

| Feature / Funcionalidade | Standard AI Chat Flow | With ElePHPantMemory 🐘 |
| :--- | :---: | :---: |
| **API Token Costs** / *Custos de Tokens* | 📈 Increases exponentially | 📉 Flat and optimized |
| **Memory Retention** / *Retenção de Memória* | ❌ Forgets when context limits hit |  Never forgets core facts |
| **Dependencies** / *Dependências* | 📦 Heavy framework overhead | ❌ Zero (Vanilla PHP) |
| **Database Setup** / *Configuração de BD* | ⚙️ Requires external Vector DB |  Reuses your active connection |
| **GDPR Compliance** / *Pronto para RGPD* | 🛠️ Manual code required | ⚡ Automated local TTL purging |

---

## 🛠️ Quick Start / Guia Rápido

### Installation / Instalação

#### Option A: Composer (Recommended / Recomendado)
```bash
composer require elephpantmemory/memory
```

#### Option B: Manual Include / Importação Manual
Download the package files and include the main manager file:
*Descarrega os ficheiros do pacote e importa o ficheiro do gestor principal:*
```php
require_once 'ElePHPantMemory/ChatManager.php';
```

### Usage / Utilização

#### Option 1: Basic Local Storage / Armazenamento Local Simples
Manually save messages and facts (requires you to handle the AI calls manually for fact extraction).
*Regista mensagens e factos manualmente (requer que chames a IA externamente para extrair os factos).*

```php
use ElePHPantMemory\ChatManager;

// 1. Grab your project's active database connection (PDO or mysqli)
$memory = new ChatManager(
    existingConnection: $myActivePDO, 
    maxTokens: 2000, 
    purgeAfterDays: 15 // Auto-GDPR cleanup
);

$session = 'whatsapp_user_912345678';

// 2. Log messages normally (respects maxTokens sliding window)
$memory->addMessage($session, 'user', 'Hello, my name is Ze Povinho and my VAT is 123456789.');

// 3. Save extracted facts manually
$memory->saveFact($session, 'user_name', 'Ze Povinho');
$memory->saveFact($session, 'vat_number', '123456789');

// 4. Build the optimized payload ready for your AI API call
$aiPayload = $memory->getOptimizedContext($session);
```

#### Option 2: Intelligent Auto-Memory (Zero-Dependency AI Integration)
Let ElePHPantMemory automatically extract facts from chats and summarize old conversations using OpenAI or Gemini natively (via cURL).
*Deixa o ElePHPantMemory extrair factos e sumarizar conversas antigas automaticamente usando OpenAI ou Gemini nativo (via cURL).*

```php
use ElePHPantMemory\ChatManager;
use ElePHPantMemory\GeminiProvider; // Or OpenAIProvider / CustomProvider

// 1. Instantiate the AI Provider (Zero external packages needed!)
// 1. Instancia o Provedor de IA (Sem pacotes externos adicionais!)
$ai = new GeminiProvider(apiKey: 'YOUR_GEMINI_API_KEY', model: 'gemini-1.5-flash');
// Or: $ai = new OpenAIProvider(apiKey: 'YOUR_OPENAI_API_KEY', model: 'gpt-4o-mini');

$memory = new ChatManager(
    existingConnection: $myActivePDO,
    maxTokens: 1500,
    purgeAfterDays: 30,
    aiProvider: $ai
);

// Enable automatic facts extraction on addMessage()
// Ativa a extração automática de factos ao adicionar mensagens
$memory->autoExtractFacts = true;

$session = 'user_session_123';

// 2. Add message. Fact extraction will trigger automatically!
// 2. Adiciona mensagem. A extração de factos é disparada automaticamente!
$memory->addMessage($session, 'user', 'Hi, my name is Rui, I live in Lisbon and prefer PHP.');

// 3. The sliding window automatically summarizes oldest messages when maxTokens is hit!
// 3. A janela deslizante sumariza automaticamente as mensagens antigas quando excede os maxTokens!
// Purged history is saved under the special fact '_conversation_summary'
$aiPayload = $memory->getOptimizedContext($session);

// 4. Get a specific fact / Obter um facto específico
$userName = $memory->getFact($session, 'user_name'); // 'Rui'
```

#### Option 3: Memory Lifecycle Operations / Gestão do Ciclo de Vida da Memoria
```php
// Delete a specific fact / Apaga um facto específico
$memory->deleteFact($session, 'vat_number');

// Clear all facts / Limpa todos os factos
$memory->clearFacts($session);

// Clear message history / Limpa o histórico de mensagens
$memory->clearHistory($session);

// Delete entire session data / Apaga todos os dados da sessão
$memory->deleteSession($session);
```

---

## 🏗️ Requirements / Requisitos

* PHP 8.4+ (Utilizes cutting-edge property hooks and asymmetric visibility).
* An active database connection instance using `PDO` (MySQL, SQLite, SQL Server, PostgreSQL) or `mysqli`.

---

## 🛡️ Error Handling / Tratamento de Erros

### English
ElePHPantMemory throws custom exceptions under the `ElePHPantMemory` namespace, all of which extend `ElePHPantException`:
* **`ValidationException`**: Thrown when configuration parameters are invalid (e.g. `maxTokens` limit is below 500).
* **`StorageException`**: Thrown when database connection types are unsupported, queries fail, or prepared statement operations fail.
* **`AIException`**: Thrown when cURL connection fails, API responds with error, or response JSON mapping fails.

```php
try {
    $memory = new ChatManager($db, 2000, 30, $ai);
    $memory->addMessage($session, 'user', 'Hello!');
} catch (\ElePHPantMemory\ValidationException $e) {
    echo "Validation failed: " . $e->getMessage();
} catch (\ElePHPantMemory\StorageException $e) {
    echo "Database error: " . $e->getMessage();
} catch (\ElePHPantMemory\AIException $e) {
    echo "AI Provider error: " . $e->getMessage();
} catch (\ElePHPantMemory\ElePHPantException $e) {
    echo "General library error: " . $e->getMessage();
}
```

### Português (Portugal)
O ElePHPantMemory lança exceções customizadas sob o namespace `ElePHPantMemory`, herdando todas de `ElePHPantException`:
* **`ValidationException`**: Lançada quando os parâmetros de configuração são inválidos (ex: limite de `maxTokens` inferior a 500).
* **`StorageException`**: Lançada quando a ligação à base de dados não é suportada, as queries falham ou ocorrem erros ao preparar statements.
* **`AIException`**: Lançada quando a ligação cURL falha, a API retorna erros ou a conversão de JSON falha.

```php
try {
    $memory = new ChatManager($db, 2000, 30, $ai);
    $memory->addMessage($session, 'user', 'Olá!');
} catch (\ElePHPantMemory\ValidationException $e) {
    echo "Validação falhou: " . $e->getMessage();
} catch (\ElePHPantMemory\StorageException $e) {
    echo "Erro de base de dados: " . $e->getMessage();
} catch (\ElePHPantMemory\AIException $e) {
    echo "Erro no Provedor de IA: " . $e->getMessage();
} catch (\ElePHPantMemory\ElePHPantException $e) {
    echo "Erro genérico da biblioteca: " . $e->getMessage();
}
```

---

## 🧪 Testing / Testes

### English
You can run the built-in, zero-dependency test suite using the following command:

### Português (Portugal)
Podes correr a suite de testes integrada e sem dependências usando o seguinte comando:

```bash
php run_tests.php
```

---

## 🤝 Contributing / Contribuir

### English
Contributions are welcome! Please check [CONTRIBUTING.md](CONTRIBUTING.md) for architecture guidelines. Keep it dependency-free and ASCII-clean in PHP code.

### Português (Portugal)
Contribuições são bem-vindas! Verifica o ficheiro [CONTRIBUTING.md](CONTRIBUTING.md) para diretrizes de arquitetura. Mantém o código PHP livre de dependências externas e sem acentos nos ficheiros de código.

---

## ⚖️ License / Licença

### English
This project is licensed under the **GNU General Public License v3.0 (GPL-3.0)** - see the [LICENSE](LICENSE) file for details. Copyleft protects your work: anyone distributing modifications to this library must share them openly under the same terms.

### Português (Portugal)
Este projeto está licenciado sob a **GNU General Public License v3.0 (GPL-3.0)** - consulta o ficheiro [LICENSE](LICENSE) para mais detalhes. O Copyleft protege o teu trabalho: qualquer pessoa que distribua modificações desta biblioteca é obrigada a partilhá-las abertamente sob os mesmos termos.