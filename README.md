# Moz Acad

**Tagline:** Plataforma inteligente de apoio à escrita científica, normalização institucional e geração documental académica.

## Visão geral
Moz Acad é uma base de produção MVC em PHP 8.2+ para apoio académico assistido, pricing avançado, pagamento Débito M-Pesa C2B, geração DOCX e revisão humana obrigatória para monografias.

## Stack
- PHP 8.2+
- MySQL/MariaDB
- Composer
- GuzzleHTTP
- PHPWord
- vlucas/phpdotenv
- Bootstrap (UI)

## Arquitectura
- **Controllers leves**: camada HTTP e validação superficial.
- **Services fortes**: regras de pricing, descontos, regras institucionais, integração Débito, pipeline de geração.
- **Repositories**: persistência SQL isolada.
- **Jobs**: polling de pagamentos e pipeline assíncrona.

## Estrutura de pastas
```text
/public
/app
  /Controllers
  /Models
  /Repositories
  /Services
  /Jobs
  /Middleware
  /Helpers
  /DTOs
/config
/routes
/database
  /migrations
  /seeders
/storage
  /generated
  /templates
  /logs
```

## Instalação
1. `composer install`
2. `cp .env.example .env`
3. Criar base de dados `moz_acad`
4. Executar schema SQL em `database/migrations/001_schema.sql`
5. Executar seed em `database/seeders/001_seed_base.sql` (ou `php database/seeders/SeederRunner.php`)
6. Configurar web root para `public/`

## Configuração `.env`
Use `.env.example` como base. Parâmetros críticos:
- OpenAI/GPT-5: `AI_PROVIDER`, `OPENAI_API_KEY`, `OPENAI_MODEL*`, `OPENAI_TIMEOUT`
- Débito M-Pesa C2B: `DEBITO_BASE_URL`, `DEBITO_WALLET_ID`, `DEBITO_TOKEN`
- Callback webhook: `DEBITO_CALLBACK_URL` (ou fallback automático `APP_URL + /webhooks/debito`)
- Polling opcional: `DEBITO_POLLING_ENABLED` para auxiliar quando webhook falhar
- Validação M-Pesa: `MPESA_MSISDN_REGEX`
- Paths de storage: `STORAGE_GENERATED_PATH`, `STORAGE_TEMPLATES_PATH`, `STORAGE_LOGS_PATH`
- Uploads seguros: `STORAGE_UPLOADS_PATH`, `UPLOAD_MAX_SIZE_MB`, `UPLOAD_ALLOWED_MIME`

## Débito M-Pesa C2B
Endpoints utilizados:
- `POST /api/v1/wallets/{wallet_id}/c2b/mpesa`
- `GET /api/v1/transactions/{debito_reference}/status`

Requisito de iniciação: no fluxo M-Pesa o campo `msisdn` é obrigatório no início do pagamento.

Endpoints internos:
- `POST /payments/mpesa/initiate`
- `GET /payments/{id}/status`
- `POST /webhooks/debito`

Rotas web principais:
- `/`
- `/login`
- `/register`
- `/dashboard`
- `/orders`
- `/orders/create`
- `/orders/{id}`
- `/orders/{id}/pay`
- `/invoices`
- `/downloads`

Rotas admin mínimas:
- `/admin`
- `/admin/users`
- `/admin/orders`
- `/admin/payments`
- `/admin/discounts`
- `/admin/human-review`
- `/admin/institutions`
- `/admin/courses`
- `/admin/disciplines`
- `/admin/work-types`
- `/admin/pricing`

### Autenticação com token
A integração usa `DEBITO_TOKEN` (Bearer) como credencial principal para operações C2B M-Pesa.
`callback_url` é enviado para o Débito usando a seguinte prioridade: payload HTTP > `DEBITO_CALLBACK_URL` > `APP_URL/webhooks/debito`.

## Polling como meio principal
`PaymentStatusPollingService` é a fonte primária de confirmação. O webhook é complementar e nunca substitui o polling.

## Webhook
Rota pública: `POST /webhooks/debito`.
Recebe payload e faz reconciliação complementar de estado.
O webhook **não** sobrepõe um pagamento já marcado como `paid`; o polling continua como fonte principal.

## Cron jobs sugeridos
```bash
* * * * * php /path/to/project/scripts/poll_payments.php
*/2 * * * * php /path/to/project/scripts/process_ai_jobs.php
```
`poll_payments.php` actualiza em cascata: `payments`, `debito_transactions`, `payment_status_logs`, `invoices` e `orders`.

## Módulos implementados (execução real)
1. Autenticação e autorização (base)
2. Catálogo académico (instituições, cursos, disciplinas, níveis, tipos)
3. Pedidos e anexos
4. Pricing com breakdown
5. Cupões e descontos por utilizador
6. Facturação
7. Pagamentos Débito M-Pesa C2B
8. Polling e webhook opcional
9. Rule engine institucional (base)
10. Pipeline de geração académica
11. Humanização pt_MZ
12. Geração DOCX
13. Fila de revisão humana
14. Painéis user/admin (base)
15. Logs/auditoria e relatórios (fundação em tabelas)

## Regras especiais de monografia
- `allows_full_auto_generation = false`
- `requires_human_review = true`
- Fluxo obrigatório: gerar → revisão humana → aprovação → ready.

## Deploy em host PHP tradicional
- Compatível com `public_html`.
- Sem Docker obrigatório.
- Sem Node.js obrigatório no core.

## Uso do painel admin
Admin gerencia utilizadores, regras, pricing, descontos, pedidos, pagamentos, revisão humana e templates.

## Uso de descontos para utilizadores seleccionados
- Tipos: `percent`, `fixed`, `extra_waiver`
- Controle por validade, limite, tipo de trabalho e extra.
- Uso auditável em `discount_usage_logs`.

## Notas de produção
- Sessão HTTP com cookies `httponly` e `SameSite=Lax` ativa em `public/index.php`.
- Sessões endurecidas: `use_strict_mode`, `use_only_cookies` e regeneração de sessão no login/registo.
- CSRF básico ativo para operações mutáveis (auth, pedidos, pagamentos e ações admin) via `_csrf` ou header `X-CSRF-TOKEN`.
- Uploads de anexos com validação de tamanho/MIME e armazenamento em `STORAGE_UPLOADS_PATH`.
- Login e registo com hash de senha (`password_hash/password_verify`) e auditoria em `audit_logs`.
- Pipeline AI processa `ai_jobs` reais via `scripts/process_ai_jobs.php` e persiste documentos em `generated_documents`.
