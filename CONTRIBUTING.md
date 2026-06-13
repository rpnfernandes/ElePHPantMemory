# Contributing to ElePHPant 🐘💬

Thank you for taking the time to contribute to ElePHPant! Your help makes this local AI memory manager better for the entire PHP community.

*Obrigado pelo teu tempo para contribuir para o ElePHPant! A tua ajuda torna este gestor de memoria local para IA melhor para toda a comunidade PHP.*

---

## 📜 Code Quality Guidelines / Diretrizes de Qualidade de Codigo

### English
To maintain the core philosophy of ElePHPant (speed, light footprint, and ease of installation), all contributions must strictly adhere to the following rules:

1. **Zero External Dependencies:** Do not introduce Composer packages, external libraries, or vendor autoloader requirements. The project must remain 100% Vanilla PHP.
2. **ASCII Clean Code:** All file names, class structures, variables, method names, and inline code comments **must be written in English** and contain **NO special characters or accents** (`ç`, `á`, `ã`, etc.).
3. **PHP 8.4+ Standards:** Take full advantage of modern language capabilities. Use property hooks, asymmetric visibility (`public private(set)`), constructor property promotion, and strong typing wherever applicable.
4. **Database Portability:** If you modify database queries, ensure your logic remains compatible across all supported drivers (SQLite, MySQL, and SQL Server) using the match patterns inside the Storage abstraction layer.

### Portugues (Portugal)
Para manter a filosofia central do ElePHPant (velocidade, pegada leve e facilidade de instalacao), todas as contribuicoes devem seguir rigidamente as seguintes regras:

1. **Zero Dependencias Externas:** Nao introduzas pacotes do Composer, livrarias externas ou requisitos de autoloader. O projeto tem de se manter 100% PHP Vanilla.
2. **Codigo ASCII Clean:** Todos os nomes de ficheiros, estruturas de classes, variaveis, nomes de metodos e comentarios internos **tem de ser escritos em ingles** e **NAO podem conter caracteres especiais ou acentos** (`ç`, `á`, `ã`, etc.).
3. **Padroes PHP 8.4+:** Tira o maximo partido das capacidades modernas da linguagem. Usa property hooks, visibilidade assimetrica (`public private(set)`), constructor property promotion e tipagem forte sempre que aplicavel.
4. **Portabilidade de Base de Dados:** Se modificares queries, garante que a tua logica se mantem compativel com todos os motores suportados (SQLite, MySQL e SQL Server) utilizando os padroes de match dentro da camada de abstracao do Storage.

---

## 🚀 How to Submit Changes / Como Submeter Alteracoes

### English
1. **Fork** the repository on GitHub.
2. **Clone** your fork locally and create a descriptive feature branch:
   <pre><code>git checkout -b feature/my-amazing-feature</code></pre>
3. **Implement** your changes following our code quality rules.
4. **Test** your code thoroughly using different database drivers if possible.
5. **Commit** your changes using clear, precise commit messages:
   <pre><code>git commit -m "Fix SQL Server table creation constraint issue"</code></pre>
6. **Push** your branch to your fork:
   <pre><code>git push origin feature/my-amazing-feature</code></pre>
7. Open a **Pull Request** against the `main` branch of the official ElePHPant repository.

### Portugues (Portugal)
1. Faz **Fork** do repositorio no GitHub.
2. **Clona** o teu fork localmente e cria uma branch descritiva para a funcionalidade:
   <pre><code>git checkout -b feature/minha-funcionalidade-incrivel</code></pre>
3. **Implementa** as tuas alteracoes seguindo as nossas regras de qualidade de codigo.
4. **Testa** o teu codigo cuidadosamente usando diferentes motores de base de dados se possivel.
5. Guarda as alteracoes (**Commit**) usando mensagens claras e precisas:
   <pre><code>git commit -m "Fix SQL Server table creation constraint issue"</code></pre>
6. Envia a branch (**Push**) para o teu fork:
   <pre><code>git push origin feature/minha-funcionalidade-incrivel</code></pre>
7. Abre um **Pull Request** direcionado à branch `main` do repositorio oficial do ElePHPant.

---

## ⚖️ Code of Conduct & Copyleft Notice / Codigo de Conduta e Aviso de Copyleft

### English
By contributing to this project, you agree that your code will be licensed under the **GNU General Public License v3.0 (GPL-3.0)**. This ensures that the code remains free and open forever. Any downstream modifications distributed by the community must also be open source.

### Portugues (Portugal)
Ao contribuir para este projeto, concordas que o teu codigo sera licenciado sob a **GNU General Public License v3.0 (GPL-3.0)**. Isto garante que o codigo permanece livre e aberto para sempre. Quaisquer modificacoes futuras distribuidas pela comunidade também terao de ser open source.