# Arquitectura Moz Acad

## Camadas
- **Presentation:** Controllers + Views + rotas.
- **Application:** Services e Jobs.
- **Domain:** DTOs e regras de negócio.
- **Infrastructure:** Repositories, DB, integração Débito, filesystem DOCX.

## Fluxos críticos
1. Criação de pedido em wizard (5 etapas)
2. Cálculo de preço + desconto de utilizador
3. Geração de factura + init pagamento M-Pesa
4. Polling de estado (principal) + webhook (complementar)
5. Pipeline de geração, humanização pt_MZ, formatação institucional, DOCX
6. Encaminhamento para revisão humana quando obrigatório (monografia)

## Árvore de ficheiros
```text
.
├── app
│   ├── Controllers
│   ├── DTOs
│   ├── Helpers
│   ├── Jobs
│   ├── Middleware
│   ├── Models
│   ├── Repositories
│   └── Services
├── bootstrap
├── config
├── database
│   ├── migrations
│   └── seeders
├── docs
├── public
├── routes
├── scripts
├── storage
│   ├── generated
│   ├── logs
│   └── templates (candidatos; montagem actual continua programática)
└── views
```
## Normas institucionais (fallback PDF/OCR)

- O pipeline tenta carregar por ordem: `norma.txt` → `metadata.normalized_text` → extração de `norma.pdf` com `pdftotext`.
- Se `pdftotext` falhar, tenta OCR local com `ocrmypdf` e nova extração para texto.
- Em ambientes `public_html` sem utilitários nativos, usa OCR remoto via `NORM_OCR_PIPELINE_ENDPOINT` (upload do PDF, polling com timeout/retry, download de texto).
- Quando OCR é bem-sucedido, persiste texto normalizado em `norma.txt` e `metadata.normalized_text` para evitar retrabalho.
- Se todas as estratégias falharem, o contexto retorna `source=pdf_unparsed`; a activação administrativa da norma é bloqueada com mensagem acionável para produção.



## Operação de produção para IA

### 1) Política de retry/backoff (timeouts e HTTP 429)
- Aplicar retry apenas para falhas transitórias: `timeout`, `connection reset`, HTTP `429`, HTTP `502/503/504`.
- Não aplicar retry para erros funcionais (HTTP `400/401/403/404`) e validações de payload.
- Estratégia recomendada: **exponential backoff com jitter**.
  - Tentativas: `3` (além da tentativa inicial).
  - Espera base: `500ms`; factor `2x`; teto por tentativa: `8s`.
  - Jitter: `±20%` para reduzir thundering herd.
- Para HTTP `429`, respeitar `Retry-After` quando presente; quando ausente, usar backoff padrão.
- Registrar em log cada retry com: `provider`, `modelo`, `attempt`, `wait_ms`, `erro_resumido`.

### 2) Observabilidade mínima em logs
- Medir e registrar por chamada: `latency_ms`, `provider`, `modelo`, `operation` (generate/refine/humanize/structured).
- Registrar status HTTP (`http_status`) e tipo de falha (`timeout`, `rate_limit`, `server_error`, `invalid_response`).
- Marcar resposta incompleta com evento dedicado (ex.: `response_incomplete=true`) incluindo causa técnica conhecida.
- Marcar fallback acionado com: `fallback_used=true`, `from_provider`, `to_provider`, `motivo`.
- Padronizar logs em JSON e incluir `correlation_id` por pedido para rastreabilidade ponta-a-ponta.

### 3) Alertas operacionais por provider/modelo
- Criar alertas por janela móvel (5min/15min):
  - Taxa de falha total por `provider+modelo` acima do limiar (ex.: `>8%` por 15min).
  - Pico de HTTP `429` por `provider+modelo` (ex.: `>3%` por 5min).
  - Aumento de fallback (ex.: `fallback_used` > `10%` por 15min).
  - Latência p95 acima do SLO definido por operação.
- Direcionar alerta para canal de on-call com payload mínimo: serviço, provider, modelo, período, métrica, valor observado e limiar.

### 4) Limites de custo/tokens
- Definir guardrails por pedido:
  - `max_input_tokens` e `max_output_tokens` por operação.
  - `max_total_tokens_per_request` (soma de tentativas/fallbacks).
  - `max_cost_per_request` em moeda de controlo.
- Definir guardrails diários:
  - `max_total_tokens_per_day` por ambiente (prod/staging).
  - `max_cost_per_day` por provider e global.
- Ao atingir limite: bloquear novas execuções não críticas e responder com erro operacional explícito; manter trilha de auditoria.

### 5) Contingência sem deploy
- Operar seleção de provider/modelo via configuração externa (`env`/feature flag) para troca imediata sem publicação de código.
- Manter matriz de contingência pré-aprovada com ordem de failover por operação.
- Procedimento de incidente:
  1. Identificar provider/modelo degradado.
  2. Alterar configuração ativa para secundário (ou modo `single` temporário).
  3. Validar smoke test funcional.
  4. Monitorar estabilização por 30 minutos.
  5. Registrar incidente e plano de reversão.
- Garantir que a troca seja auditável (quem alterou, quando, valor anterior/novo).

## Convergência fresh vs upgrade
- `database/setup.php` aplica schema base/migrations e executa `SchemaConvergenceService` para garantir equivalência estrutural efectiva.
- `scripts/validate_runtime.php` faz smoke checks de fluxos críticos e inconsistências operacionais.
