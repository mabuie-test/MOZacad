# Política de Logging Seguro (Operacional)

## Pode ser registado
- `request_id`, método HTTP, URI, estado interno/externo.
- IDs técnicos (`payment_id`, `order_id`, `queue_id`, `invoice_id`).
- Resumos de payload/response (apenas chaves e campos operacionais mínimos).

## Deve ser truncado
- Strings extensas (> 2048 chars) são truncadas automaticamente.
- Mensagens de provider são reduzidas para amostras curtas.

## Deve ser mascarado
- `msisdn`: mantém prefixo/sufixo e mascara dígitos intermédios.
- Campos com nomes sensíveis (`*token*`, `*secret*`, `*signature*`, `authorization`) são redigidos.

## Nunca persistir em claro
- Tokens de autenticação, segredos, API keys, passwords.
- Headers completos de autorização/assinatura.
- Payloads integrais de pagamento quando não estritamente necessários.

## Rotação mínima
- `storage/logs/debito.log` e `storage/logs/application.log` rodam automaticamente ao atingir `LOG_MAX_FILE_SIZE_MB`.

## Compliance da trilha de auditoria
- **Pesquisa Admin/API**: suporta filtros por `actor_id`, `action`, período (`from`/`to`), pedido (`order_id`) e entidade (`subject_type` + `subject_id`).
- **Exportação externa**: endpoint `/api/admin/audit-logs/export` com `format=json|csv`.
- **Alertas críticos**: eventos `admin.payment.confirm_manual`, `admin.human_review.decided` e `admin.template_artifact.activated` geram entrada `audit.critical_event` no `application.log`.
- **Integridade anti-adulteração**: cada evento grava `previous_hash` + `event_hash` (SHA-256 encadeado).
- **Retenção**:
  - Hot storage: 180 dias na tabela `audit_logs`.
  - Arquivo: até 5 anos em armazenamento externo com checksum SHA-256 em `audit_log_archives`.
  - Purga: após 5 anos, conforme calendário legal/contratual aplicável.
