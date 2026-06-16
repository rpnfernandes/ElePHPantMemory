# Contributing to ElePHPantMemory 🐘💬

Thank you for taking the time to contribute! Your help makes this local AI memory manager better for the entire PHP community.

*Obrigado por tirares um tempo para contribuir! A tua ajuda é fundamental para tornar este gestor de memória local para IA ainda melhor para toda a comunidade PHP.*

---

## 📜 Code Quality Guidelines / Diretrizes de Qualidade de Código

### English
To maintain the core philosophy of ElePHPantMemory (speed, light footprint, and ease of installation), all contributions must strictly adhere to the following rules:

1. **Zero External Dependencies:** Do not introduce Composer packages, external libraries, or vendor autoloader requirements. The project must remain 100% Vanilla PHP. This includes any new AI providers, which must be implemented using native PHP extensions (like `curl`) or custom callbacks.
2. **ASCII Clean Code:** All file names, class structures, variables, method names, and inline code comments **must be written in English** and contain **NO special characters or accents** (`ç`, `á`, `ã`, etc.). This restriction applies only to PHP code files; markdown documentation should use correct accents.
3. **PHP 8.4+ Standards:** Take full advantage of modern language capabilities. Use property hooks, asymmetric visibility (`public private(set)`), constructor property promotion, and strong typing wherever applicable.
4. **Database Portability:** If you modify database queries, ensure your logic remains compatible across all supported drivers (SQLite, MySQL, PostgreSQL, and SQL Server) using the match patterns inside the Storage abstraction layer.

### Português (Portugal)
Para manter a filosofia central do ElePHPantMemory (velocidade, simplicidade, leveza e facilidade de instalação), todas as contribuições devem seguir as seguintes regras à risca:

1. **Zero Dependências Externas:** Não introduzas pacotes do Composer, livrarias externas ou requisitos de autoloader. O projeto tem de se manter 100% PHP Vanilla. Isto aplica-se também a novos provedores de IA, que devem usar extensões nativas (como a `curl`) ou callbacks customizados.
2. **Código ASCII Clean:** Todos os nomes de ficheiros, classes, variáveis, métodos e comentários inline **devem ser escritos em inglês** e **não podem conter caracteres especiais ou acentos** (`ç`, `á`, `ã`, etc.). Esta restrição aplica-se apenas aos ficheiros de código PHP; a documentação em markdown pode e deve usar acentos normalmente.
3. **Padrões PHP 8.4+:** Aproveita ao máximo as novidades da linguagem. Usa property hooks, visibilidade assimétrica (`public private(set)`), constructor property promotion e tipagem forte sempre que fizer sentido.
4. **Portabilidade da Base de Dados:** Se alterares ou adicionares queries SQL, garante que a tua lógica é compatível com todos os motores suportados (SQLite, MySQL, PostgreSQL e SQL Server) usando os blocos `match` na camada de abstração `Storage`.

---

## 🚀 How to Submit Changes / Como Submeter Alterações

### English
1. **Fork** the repository on GitHub.
2. **Clone** your fork locally and create a descriptive feature branch:
   <pre><code>git checkout -b feature/my-amazing-feature</code></pre>
3. **Implement** your changes following our code quality rules.
4. **Test** your code thoroughly. Always run the integrated test suite:
   <pre><code>php run_tests.php</code></pre>
5. **Commit** your changes using clear, precise commit messages:
   <pre><code>git commit -m "Fix SQL Server table creation constraint issue"</code></pre>
6. **Push** your branch to your fork:
   <pre><code>git push origin feature/my-amazing-feature</code></pre>
7. Open a **Pull Request** against the `main` branch of the official ElePHPantMemory repository.

### Português (Portugal)
1. Faz **Fork** do repositório no GitHub.
2. **Clona** o teu fork localmente e cria uma branch descritiva para a funcionalidade:
   <pre><code>git checkout -b feature/minha-funcionalidade-incrivel</code></pre>
3. **Implementa** as tuas alterações seguindo as nossas regras de qualidade de código.
4. **Testa** o teu código com cuidado. Corre sempre a suite de testes integrada:
   <pre><code>php run_tests.php</code></pre>
5. Guarda as alterações (**Commit**) usando mensagens claras e precisas:
   <pre><code>git commit -m "Fix SQL Server table creation constraint issue"</code></pre>
6. Envia a branch (**Push**) para o teu fork:
   <pre><code>git push origin feature/minha-funcionalidade-incrivel</code></pre>
7. Abre um **Pull Request** direcionado à branch `main` do repositório oficial do ElePHPantMemory.

---

## ⚖️ Code of Conduct & Copyleft Notice / Código de Conduta e Aviso de Copyleft

### English
By contributing to this project, you agree that your code will be licensed under the **GNU General Public License v3.0 (GPL-3.0)**. This ensures that the code remains free and open forever. Any downstream modifications distributed by the community must also be open source.

### Português (Portugal)
Ao contribuir para este projeto, concordas que o teu código será licenciado sob a **GNU General Public License v3.0 (GPL-3.0)**. Isto garante que o código permanece livre e aberto para sempre. Quaisquer modificações futuras distribuídas pela comunidade também terão de ser publicadas como open source sob os mesmos termos.