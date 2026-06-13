# ElePHPant 🐘💬

[![Version](https://img.shields.io/badge/version-1.0.0-green.svg)](#)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-8892BF.svg)](https://www.php.net)

An ultra-lightweight, zero-dependency local memory and context manager for AI chatbots. 
Written in pure, modern PHP 8.4+.

*Gestor de memoria e contexto local para chatbots de IA, ultra leve e sem dependencias. 
Escrito em PHP 8.4+ puro e moderno.*

---

## 💡 The Problem / O Problema

### English
LLM APIs (OpenAI, Claude, Gemini) are **stateless** and **expensive**. To maintain a conversation, developers usually send the entire chat history back and forth. As conversations grow, token consumption skyrockets, API bills explode, and the AI starts to lose track of the core topic.

### Portugues (Portugal)
As APIs de IA sao **stateless** (nao guardam estado) e **caras**. Para manter uma conversa, os programadores enviam todo o historico para a API a cada nova mensagem. Conforme a conversa cresce, o consumo de tokens dispara, a fatura da API explode e a IA comeca a perder o fio à meada.

---

## 🚀 The Solution: How ElePHPant Works / A Solucao

### English
ElePHPant sits between your application and the AI API. It manages chat context using a two-tier architecture running entirely inside your host project's existing database:
1. **The Short-Term Memory:** Keeps only the most recent messages to maintain natural conversation flow.
2. **The Long-Term Fact Sheet:** Extracts and preserves permanent business facts (e.g., user preferences, order IDs, VAT numbers) in a dedicated local table, injecting them as system prompts.

### Portugues (Portugal)
O ElePHPant funciona como um filtro inteligente entre a tua aplicacao e a API de IA. Ele gere o contexto usando uma arquitetura de duas camadas que corre inteiramente dentro da base de dados existente do teu projeto:
1. **Memoria de Curto Prazo:** Mantem apenas as mensagens mais recentes para garantir a fluidez natural da conversa.
2. **Ficha de Factos de Longo Prazo:** Extrai e preserva factos permanentes do negocio (ex: preferencias, IDs de encomendas, NIFs) numa tabela local, injetando-os como prompts de sistema.

---

## 📊 Features & Comparison / Carateristicas e Comparacao

| Feature / Funcionalidade | Standard AI Chat Flow | With ElePHPant 🐘 |
| :--- | :---: | :---: |
| **API Token Costs** / *Custos de Tokens* | 📈 Increases exponentially | 📉 Flat and optimized |
| **Memory Retention** / *Retencao de Memoria* | ❌ Forgets when context limits hit |  Never forgets core facts |
| **Dependencies** / *Dependencias* | 📦 Heavy framework overhead | ❌ Zero (Vanilla PHP) |
| **Database Setup** / *Configuracao de BD* | ⚙️ Requires external Vector DB |  Reuses your active connection |
| **GDPR Compliance** / *Pronto para RGPD* | 🛠️ Manual code required | ⚡ Automated local TTL purging |

---

## 🛠️ Quick Start / Guia Rapido

### Installation / Instalacao

#### Option A: Composer (Recommended / Recomendado)
```bash
composer require elephpant/memory
```

#### Option B: Manual Include / Importacao Manual
Download the package files and include the main manager file:
*Descarrega os ficheiros do pacote e importa o ficheiro do gestor principal:*
```php
require_once 'ElePHPant/ChatManager.php';
```

### Usage / Utilizacao

```php
use ElePHPant\ChatManager;

// 1. Grab your project's active database connection (PDO or mysqli)
// 1. Usa a ligacao ativa do teu projeto (PDO ou mysqli)
$memory = new ChatManager(
    existingConnection: $myActivePDO, 
    maxTokens: 2000, 
    purgeAfterDays: 15 // Auto-GDPR cleanup
);

$session = 'whatsapp_user_912345678';

// 2. Log messages normally (automatically respects maxTokens sliding window)
// 2. Regista as mensagens normalmente (respeita automaticamente a janela de maxTokens)
$memory->addMessage($session, 'user', 'Hello, my name is Ze Povinho and my VAT is 123456789.');

// 3. Save extracted metadata (Use your AI function-calling to extract these)
// 3. Guarda metadados extraidos (Usa o function-calling da tua IA para extrair isto)
$memory->saveFact($session, 'user_name', 'Ze Povinho');
$memory->saveFact($session, 'vat_number', '123456789');

// 4. Build the tight, optimized payload ready for your AI API call
// 4. Constroi o payload otimizado e leve pronto para enviar para a API da IA
$aiPayload = $memory->getOptimizedContext($session);

// 5. Memory Lifecycle Operations / Gestao do Ciclo de Vida da Memoria
// Delete a specific fact / Apaga um facto especifico
$memory->deleteFact($session, 'vat_number');

// Clear all facts / Limpa todos os factos
$memory->clearFacts($session);

// Clear message history / Limpa o historico de mensagens
$memory->clearHistory($session);

// Delete entire session data / Apaga todos os dados da sessao
$memory->deleteSession($session);
```

---

## 🏗️ Requirements / Requisitos

* PHP 8.4+ (Utilizes cutting-edge property hooks and asymmetric visibility).
* An active database connection instance using `PDO` (MySQL, SQLite, SQL Server, PostgreSQL) or `mysqli`.

---

## 🛡️ Error Handling / Tratamento de Erros

### English
ElePHPant throws custom exceptions under the `ElePHPant` namespace:
* **`ValidationException`**: Thrown when configuration parameters are invalid (e.g. `maxTokens` limit is below 500).
* **`StorageException`**: Thrown when database connection types are unsupported, queries fail, or prepared statement operations fail.

### Portugues (Portugal)
O ElePHPant lanca excecoes customizadas sob o namespace `ElePHPant`:
* **`ValidationException`**: Lancada quando os parametros de configuracao sao invalidos (ex: limite de `maxTokens` inferior a 500).
* **`StorageException`**: Lancada quando a ligacao de base de dados nao e suportada, as queries falham, ou ocorrem erros ao preparar statements.

```php
try {
    $memory = new ChatManager($db, 400); // Throws ValidationException / Lanca ValidationException
} catch (\ElePHPant\ValidationException $e) {
    echo "Validation failed / Validacao falhou: " . $e->getMessage();
} catch (\ElePHPant\StorageException $e) {
    echo "Database error / Erro de base de dados: " . $e->getMessage();
}
```

---

## 🧪 Testing / Testes

### English
You can run the built-in, zero-dependency test suite using the following command:

### Portugues (Portugal)
Podes correr a suite de testes integrada e sem dependencias usando o seguinte comando:

```bash
php run_tests.php
```

---

## 🤝 Contributing / Contribuir

### English
Contributions are welcome! Please check [CONTRIBUTING.md](CONTRIBUTING.md) for architecture guidelines. Keep it dependency-free and ASCII-clean.

### Portugues (Portugal)
Contribuicoes sao bem-vindas! Verifica o ficheiro [CONTRIBUTING.md](CONTRIBUTING.md) para diretrizes de arquitetura. Mantem o codigo livre de dependencias e limpo de acentos.

---

## ⚖️ License / Licenca

### English
This project is licensed under the **GNU General Public License v3.0 (GPL-3.0)** - see the [LICENSE](LICENSE) file for details. Copyleft protects your work: anyone distributing modifications to this library must share them openly under the same terms.

### Portugues (Portugal)
Este projeto esta licensed sob a **GNU General Public License v3.0 (GPL-3.0)** - consulta o ficheiro [LICENSE](LICENSE) para mais detalhes. O Copyleft protege o teu trabalho: qualquer pessoa que distribua modificacoes desta biblioteca e obrigada a partilha-las abertamente sob os mesmos termos.