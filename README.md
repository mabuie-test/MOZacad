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
- Débito: `DEBITO_BASE_URL`, `DEBITO_WALLET_ID`, token/credenciais
- Pricing fallback: `PRICING_*`
- Webhook opcional: `DEBITO_ENABLE_WEBHOOK`, `DEBITO_CALLBACK_URL`

## Débito M-Pesa C2B
Endpoints utilizados:
- `POST /api/v1/login`
- `POST /api/v1/wallets/{wallet_id}/c2b/mpesa`
- `GET /api/v1/transactions/{debito_reference}/status`

### Token estático
Se `DEBITO_USE_STATIC_TOKEN=true` e `DEBITO_TOKEN` definido, o sistema usa bearer estático.

### Login dinâmico
Se token estático não estiver disponível, o sistema autentica com `DEBITO_EMAIL` + `DEBITO_PASSWORD`.

## Polling como meio principal
`PaymentStatusPollingService` é a fonte primária de confirmação. O webhook é complementar e nunca substitui o polling.

## Webhook opcional
Rota: `POST /webhooks/debito`.
Recebe payload, regista logs e permite sincronização extra.

## Cron jobs sugeridos
```bash
* * * * * php /path/to/project/scripts/poll_payments.php
*/2 * * * * php /path/to/project/scripts/process_ai_jobs.php
```

## Módulos implementados
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
- Adicionar middleware de sessão segura, CSRF e rate limit antes de go-live.
- Substituir stubs de Auth e AI provider por integrações reais.
- Adicionar testes automatizados e observabilidade centralizada.
