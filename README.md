# MOZacad

Plataforma MVC em PHP 8.2+ para pedidos académicos, pagamento Débito M-Pesa C2B, geração DOCX, revisão humana e entrega segura, com autorização centralizada e observabilidade operacional.

## Setup rápido (instalação nova)
1. `composer install`
2. `cp .env.example .env`
3. Criar base `moz_acad`
4. Aplicar schema canónico actual (sem cadeia histórica de migrations):
   - `php database/setup.php --fresh`
   - ou `composer db:schema`
5. (Opcional) carregar dados base:
   - `php database/setup.php --fresh --seed`
   - ou `composer db:setup`
6. Servir `public/` como document root (`public_html` compatível).

## Upgrade de instalações antigas
Para ambientes já existentes, manter caminho incremental:
1. Fazer backup da base.
2. Executar migrations incrementais:
   - `php database/setup.php --upgrade`
   - ou `composer db:upgrade`
3. A migration `006_runtime_schema_alignment.sql` fecha lacunas de runtime (incluindo `ai_jobs` e integridade mínima de pagamentos/cupões) em bases que não tenham aplicado toda a cadeia histórica.
4. A migration `007_human_review_cycle_convergence.sql` alinha o ciclo de revisão/regeneração (vínculo explícito a versão documental).
5. `database/setup.php` executa verificação/repair de convergência de schema no final (desactivar apenas com `--no-verify`).

## Filosofia de persistência
- **Instalação nova:** usa `database/schema/base_schema.sql` como fonte canónica do estado actual.
- **Upgrade:** usa `database/migrations/*.sql` para evolução incremental de bases já em produção.
- **Sem dupla verdade:** schema base e runtime são mantidos alinhados; migrations passam a servir upgrade/evolução.

## Fluxo real ponta-a-ponta
1. Utilizador cria pedido (`orders`).
2. Sistema calcula pricing (regras + extras + descontos).
3. Gera/reaproveita invoice aberta.
4. Inicia pagamento Débito C2B (idempotente por pedido com lock).
5. Confirmação principal por polling (`scripts/poll_payments.php`).
6. Webhook Débito é complementar (não regressa estado `paid`).
7. Ao transitar para `paid`, sistema enfileira `ai_jobs` (`stage=document_generation`) sem duplicação de job aberto.
8. `scripts/process_ai_jobs.php` processa fila real e cria versão em `generated_documents`.
9. Se tipo exigir revisão humana, entra em `human_review_queue`; admin aprova/rejeita.
10. Documento aprovado (ou `generated` sem revisão obrigatória) pode ser descarregado por rota segura.

## Pagamentos Débito (robustez)
- Polling continua como fonte primária.
- Webhook com validação defensiva opcional por HMAC (`DEBITO_WEBHOOK_SECRET`).
- Cliente HTTP com timeout, retry e backoff simples (`DEBITO_HTTP_RETRIES`, `DEBITO_HTTP_BACKOFF_MS`).
- Batch de polling configurável (`DEBITO_POLLING_BATCH_LIMIT`).
- Tratamento de respostas não-JSON e erros transitórios.
- `paid` é terminal na máquina de estados.

### Endpoint webhook
A rota é configurável por `.env`:
- `DEBITO_WEBHOOK_PATH=/webhooks/debito`

## Validação operacional rápida
- `composer ops:validate`
- verifica convergência de schema + consistência mínima de pagamentos, jobs, revisão humana, regeneração e cupões.


## Workers em desenvolvimento/Termux
Em ambiente local (Termux/dev), o worker deve ficar activo em terminal dedicado. Se o worker não estiver activo, pedidos pagos podem permanecer em `queued` (estado que significa “aguarda worker de geração documental”).

Terminal 1:
```bash
php -S 0.0.0.0:8080 -t public
```

Terminal 2:
```bash
composer workers:run
```

Rodada única (diagnóstico/manual):
```bash
composer workers:once
```

Diagnóstico da fila:
```bash
composer queue:status
```

## Workers em produção (`public_html`/hosting clássico)
Em produção com hosting clássico, **não** depender de terminal aberto com `composer workers:run`. O recomendado é cron executando rodada única:

```bash
* * * * * /usr/bin/php /home/USUARIO/mozacad/scripts/run_workers.php --once >> /home/USUARIO/mozacad/storage/logs/worker-cron.log 2>&1
```

Em alguns ambientes (ex.: cPanel), pode ser necessário:

```bash
* * * * * /usr/local/bin/php /home/USUARIO/mozacad/scripts/run_workers.php --once >> /home/USUARIO/mozacad/storage/logs/worker-cron.log 2>&1
```

Substitua `/home/USUARIO/mozacad` pelo caminho real do projecto.

Boas práticas de deploy em host com `public_html`:
- Servir apenas o directório `public/` como document root.
- Manter `app`, `vendor`, `storage`, `scripts` e `.env` fora do acesso público, sempre que possível.
- Se não for possível, proteger ficheiros sensíveis com regras adequadas em `.htaccess`.

Alternativas de contingência (apenas emergência):
- Botão admin “Processar fila agora”.
- Endpoint interno protegido (somente se não houver cron), nunca público sem token forte.

## Cron jobs
Rodada única unificada (recomendado):
```bash
* * * * * php /path/to/MOZacad/scripts/run_workers.php --once
```

Ou rotinas separadas:
```bash
* * * * * php /path/to/MOZacad/scripts/poll_payments.php
*/2 * * * * php /path/to/MOZacad/scripts/process_ai_jobs.php
*/5 * * * * php /path/to/MOZacad/scripts/reconcile_successful_payments.php
```

Variáveis operacionais úteis:
- `WORKER_RUN_ONCE`
- `WORKER_LOOP_INTERVAL_SECONDS`
- `AI_JOB_BATCH_LIMIT` (default: 5)
- `AI_JOB_STALE_PROCESSING_TIMEOUT` (default: 1800s)

## Templates institucionais (estado explícito)
- A montagem DOCX oficial é **programática** (`DocxAssemblyService`).
- `InstitutionTemplateService` apenas resolve e audita candidato em `STORAGE_TEMPLATES_PATH` sem alterar o pipeline nesta versão.

## Normas institucionais por directório
Estrutura suportada:
- `storage/norms/{institution_slug}/norma.txt`
- `storage/norms/{institution_slug}/norma.pdf`
- `storage/norms/{institution_slug}/metadata.json`

Campos relevantes em `metadata.json`:
- `normalized_text`
- `reference_style`

### Pré-requisitos por ambiente (normas em PDF)
- **Com `pdftotext`**: extração direta de texto a partir de `norma.pdf`.
- **Sem `pdftotext`, com `ocrmypdf`**: OCR local + extração subsequente com `pdftotext`.
- **Sem utilitários nativos (`public_html`)**: configurar `NORM_OCR_PIPELINE_ENDPOINT` (e opcionalmente `NORM_OCR_PIPELINE_TOKEN`) para OCR remoto com retry/polling.
- **Sem nenhuma opção de extração/OCR válida**: publicação pode ocorrer, mas **activação para produção é bloqueada** até existir `norma.txt`/texto normalizado.
- `visual_overrides`
- `front_page_overrides`
- `structure_overrides`
- `notes`

### Precedência de regras
1. Overrides em disco por instituição (`metadata.json`).
2. `institution_work_type_rules`.
3. `institution_rules`.
4. Defaults do sistema.


## Hardening e consistência adicionais
- Controllers críticos afinados com serviços de aplicação (`OrderApplicationService`, `PaymentApplicationService`, `AdminPricingService`, `AdminHumanReviewService`).
- Autorização centralizada (`AuthContextService`, `AuthorizationService`, `AdminAccessService`) para autenticação, acesso admin e ownership.
- Paths de storage ancorados com `StoragePathService` para uploads, generated, logs e norms com validação defensiva de path traversal.
- Logs operacionais em `storage/logs/application.log` para criação de pedido, transições de pagamento e processamento de AI jobs.
- Pipeline de referências com parser dedicado (`BibliographicSignalParserService`) e entradas estruturadas (`ReferenceEntryDTO`).

## Download seguro
- `GET /downloads/{documentId}`
- Verifica ownership (ou admin), estado permitido (`approved` ou `generated` com pedido `ready`), existência física e path seguro dentro de `STORAGE_GENERATED_PATH`.
- Regista auditoria de download.

## Dados mínimos seedados
- Instituições, níveis académicos, tipos de trabalho.
- Cursos, disciplinas, regras institucionais base.
- Estrutura inicial de tipo de trabalho.
- Utilizador de teste + admin com hash de password válido.

## Limitações honestas
- Validação criptográfica do webhook depende de o gateway enviar header de assinatura compatível.
- Sem credenciais reais Débito/OpenAI não é possível validar integração externa end-to-end.
- Formatação de referências gera entradas provisórias e marca necessidade de revisão manual quando metadados são incompletos.
