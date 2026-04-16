# Checklist para Deploy em Produção

## ✅ Verificações realizadas

| Item | Status |
|------|--------|
| Senhas com hash (password_hash/verify) | ✅ |
| SQL com prepared statements (anti-injection) | ✅ |
| XSS: htmlspecialchars nas views | ✅ |
| config.php no .gitignore | ✅ |
| Uploads: .htaccess bloqueia execução PHP | ✅ |
| Erro de DB: mensagem genérica, detalhe no error_log | ✅ |

---

## 📋 Passos antes de publicar

### 1. Configuração do banco
- [ ] Criar `config/config.php` a partir de `config.example.php`
- [ ] Usar **senha forte** para o usuário MySQL (não deixar vazio)
- [ ] Criar banco `bigtickets` e executar migrations em `migrations/`

### 2. .htaccess
- [ ] Ajustar `RewriteBase` conforme a URL:
  - Raiz do domínio: `RewriteBase /`
  - Subpasta: `RewriteBase /bigticketsphp/` (ou o nome da pasta)

### 3. PHP (php.ini)
- [ ] `display_errors = Off`
- [ ] `log_errors = On`
- [ ] `error_log` apontando para arquivo de log

### 4. Permissões
- [ ] `uploads/` e subpastas com permissão de escrita (775 ou 755)
- [ ] `uploads/tickets/` e `uploads/comentarios/` graváveis pelo Apache

### 5. Composer
```bash
composer install --no-dev --optimize-autoloader
```

### 6. Dados iniciais
- [ ] Executar `php migrations/zerar_banco.php` para criar admin e filial 1
- [ ] Ou criar manualmente: usuário admin, filial 1

---

## ⚠️ Segurança

- **Não** commitar `config/config.php` (já está no .gitignore)
- Trocar senha do admin após primeiro login
- Usar HTTPS em produção
- Manter PHP e dependências atualizados
