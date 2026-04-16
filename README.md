## BigTickets PHP

Versão em PHP do sistema de tickets **Big Trading**

### Estrutura inicial

- `index.php` – front controller e roteador simples.
- `bootstrap.php` – inicialização de sessão, helpers e conexão com o banco.
- `config/config.php` – configurações de banco de dados.
- `config/database.php` – criação da conexão PDO.
- `views/login.php` – tela de login (adaptada da view EJS).
- `public/style.css` – CSS compartilhado (copiado do projeto Node).

### Configuração

1. Produção: copie `config/config.example.php` para `config/config.php` e ajuste as credenciais do banco.
2. Localhost (opcional e recomendado): copie `config/config.local.example.php` para `config/config.local.php` e ajuste host/porta/usuário/senha local.
3. O sistema prioriza `config/config.local.php` quando existir. Se não existir, usa `config/config.php`.
4. Execute `php composer.phar install` (ou `composer install`) para instalar o Dompdf (exportação PDF).
5. Acesse a URL do projeto no navegador e faça login.

### Deploy em produção

1. **Banco** – Crie o banco MySQL e importe a estrutura (tabelas: `usuarios`, `filiais`, `tickets`, `comentarios`, etc.).
2. **Config** – Copie `config/config.example.php` para `config/config.php` e preencha host, usuário, senha e nome do banco.
3. **Composer** – Rode `php composer.phar install` na pasta do projeto.
4. **.htaccess** – Ajuste o `RewriteBase` no `.htaccess` conforme a URL (ex.: `/` se estiver na raiz, `/sistema/` se em subpasta).
5. **Permissões** – A pasta `uploads/` precisa de permissão de escrita (chmod 755 ou 775).
6. **PHP** – Recomendado: `display_errors = Off` no `php.ini` em produção.

