# Instalação


## Requisitos
- PHP 8.2+
- MySQL 8+
- Composer
- Apache/Nginx (rewrites habilitados)


## Passos
1. `composer install`
2. Copie `.env.example` para `.env` e ajuste credenciais.
3. Crie o banco `nimbusops` e rode os scripts em `database/migrations/*.sql`.
4. (Opcional) rode `database/seeds/seed_operations.sql` para dados mock.
5. Aponte seu VirtualHost/ServerBlock para `public/` e acesse `/`.


## Testes
- `composer test`


## Qualidade (PSR‑12)
- `composer lint`


## Notas de Segurança
- Middlewares de CORS e Security já ativos.
- Use HTTPS em produção e revise sua CSP.


## Fase 2 (Entra ID / Graph)
- Rotas `/auth/login` e `/auth/callback` podem ser adicionadas.
- Salve o `entra_object_id` em `users` e controle sessão no `AuthMiddleware`.